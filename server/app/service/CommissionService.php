<?php
declare (strict_types = 1);

namespace app\service;

use app\model\Order;
use app\model\Agent;
use app\model\Wallet;
use app\model\WalletTransaction;
use think\facade\Log;
use think\facade\Db;

class CommissionService
{
    public function calculateCommission(Order $order): array
    {
        if ($order->agent_id <= 0) {
            return ['success' => false, 'message' => '无代理关联'];
        }

        $currentAgent = Agent::where('id', $order->agent_id)->find();
        if (!$currentAgent || !$currentAgent->isNormal()) {
            return ['success' => false, 'message' => '代理不存在或已禁用'];
        }

        $commissions = [];
        $amount = floatval($order->amount);
        $currentAgentId = $currentAgent->id;
        $level = 0;
        $maxLevel = 3;

        Db::startTrans();
        try {
            while ($currentAgentId > 0 && $level < $maxLevel) {
                $agent = Agent::where('id', $currentAgentId)->lock(true)->find();
                if (!$agent) {
                    break;
                }

                if (!$agent->isNormal()) {
                    $currentAgentId = $agent->parent_agent_id;
                    $level++;
                    continue;
                }

                $rate = floatval($agent->commission_rate);
                if ($rate > 0) {
                    $commissionAmount = bcmul(strval($amount), strval($rate), 2);

                    if (floatval($commissionAmount) > 0) {
                        $wallet = Wallet::where('user_id', $agent->user_id)
                            ->where('type', 2)
                            ->lock(true)
                            ->find();

                        if (!$wallet) {
                            $wallet = new Wallet();
                            $wallet->user_id = $agent->user_id;
                            $wallet->type = 2;
                            $wallet->balance = 0;
                            $wallet->frozen = 0;
                            $wallet->save();
                        }

                        $oldFrozen = $wallet->frozen;
                        $wallet->frozen = bcadd(strval($wallet->frozen), $commissionAmount, 2);
                        $wallet->save();

                        $agent->frozen_balance = bcadd(strval($agent->frozen_balance), $commissionAmount, 2);
                        $agent->total_earnings = bcadd(strval($agent->total_earnings), $commissionAmount, 2);
                        $agent->save();

                        $settleDate = date('Y-m-d', strtotime('+1 day'));

                        $transaction = new WalletTransaction();
                        $transaction->wallet_id = $wallet->id;
                        $transaction->type = 4;
                        $transaction->amount = $commissionAmount;
                        $transaction->related_order = $order->order_no;
                        $transaction->balance_after = $wallet->frozen;
                        $transaction->settle_date = $settleDate;
                        $transaction->settle_status = 0;
                        $transaction->remark = "佣金收入（{$agent->level_text}）";
                        $transaction->save();

                        $commissions[] = [
                            'agent_id' => $agent->id,
                            'level' => $agent->level,
                            'rate' => $rate,
                            'amount' => $commissionAmount,
                        ];
                    }
                }

                $currentAgentId = $agent->parent_agent_id;
                $level++;
            }

            $totalCommission = '0.00';
            foreach ($commissions as $c) {
                $totalCommission = bcadd($totalCommission, $c['amount'], 2);
            }

            $order->commission_amount = $totalCommission;
            $order->save();

            Db::commit();

            return ['success' => true, 'commissions' => $commissions];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('calculate_commission_error', [
                'order_id' => $order->id,
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function settleDailyCommissions(): int
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $transactions = WalletTransaction::where('type', 4)
            ->where('settle_status', 0)
            ->where('settle_date', $yesterday)
            ->limit(500)
            ->select();

        $count = 0;
        foreach ($transactions as $transaction) {
            Db::startTrans();
            try {
                $affected = WalletTransaction::where('id', $transaction->id)
                    ->where('settle_status', 0)
                    ->update(['settle_status' => 1]);
                if (!$affected) {
                    Db::rollback();
                    continue;
                }

                $wallet = Wallet::where('id', $transaction->wallet_id)->lock(true)->find();
                if (!$wallet) {
                    Db::rollback();
                    continue;
                }

                $amount = $transaction->amount;

                $wallet->frozen = bcsub(strval($wallet->frozen), $amount, 2);
                $wallet->balance = bcadd(strval($wallet->balance), $amount, 2);
                $wallet->save();

                $agent = Agent::where('user_id', $wallet->user_id)->where('type', 2)->lock(true)->find();
                if ($agent) {
                    $agent->frozen_balance = bcsub(strval($agent->frozen_balance), $amount, 2);
                    $agent->available_balance = bcadd(strval($agent->available_balance), $amount, 2);
                    $agent->save();
                }

                $unfreezeTransaction = new WalletTransaction();
                $unfreezeTransaction->wallet_id = $wallet->id;
                $unfreezeTransaction->type = 5;
                $unfreezeTransaction->amount = $amount;
                $unfreezeTransaction->related_order = $transaction->related_order;
                $unfreezeTransaction->balance_after = $wallet->balance;
                $unfreezeTransaction->settle_status = 1;
                $unfreezeTransaction->remark = '佣金解冻';
                $unfreezeTransaction->save();

                Db::commit();
                $count++;
            } catch (\Exception $e) {
                Db::rollback();
                Log::error('settle_commission_error', [
                    'transaction_id' => $transaction->id,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    public function handleRefund(Order $order): array
    {
        if ($order->commission_amount <= 0) {
            return ['success' => true, 'message' => '无佣金需扣回'];
        }

        $transactions = WalletTransaction::where('related_order', $order->order_no)
            ->where('type', 4)
            ->select();

        if ($transactions->isEmpty()) {
            return ['success' => true, 'message' => '无佣金记录'];
        }

        Db::startTrans();
        try {
            foreach ($transactions as $transaction) {
                if ($transaction->settle_status == 0) {
                    $wallet = Wallet::where('id', $transaction->wallet_id)->lock(true)->find();
                    if ($wallet) {
                        $wallet->frozen = bcsub(strval($wallet->frozen), strval($transaction->amount), 2);
                        if (bccomp(strval($wallet->frozen), '0', 2) < 0) {
                            $wallet->frozen = '0.00';
                        }
                        $wallet->save();

                        $agent = Agent::where('user_id', $wallet->user_id)->lock(true)->find();
                        if ($agent) {
                            $agent->frozen_balance = bcsub(strval($agent->frozen_balance), strval($transaction->amount), 2);
                            if (bccomp(strval($agent->frozen_balance), '0', 2) < 0) {
                                $agent->frozen_balance = '0.00';
                            }
                            $agent->total_earnings = bcsub(strval($agent->total_earnings), strval($transaction->amount), 2);
                            if (bccomp(strval($agent->total_earnings), '0', 2) < 0) {
                                $agent->total_earnings = '0.00';
                            }
                            $agent->save();
                        }

                        $refundTransaction = new WalletTransaction();
                        $refundTransaction->wallet_id = $wallet->id;
                        $refundTransaction->type = 2;
                        $refundTransaction->amount = $transaction->amount;
                        $refundTransaction->related_order = $order->order_no;
                        $refundTransaction->balance_after = $wallet->balance;
                        $refundTransaction->remark = '订单退款扣回佣金';
                        $refundTransaction->save();

                        $transaction->settle_status = 2;
                        $transaction->save();
                    }
                } else {
                    $wallet = Wallet::where('id', $transaction->wallet_id)->lock(true)->find();
                    if ($wallet) {
                        $newBalance = bcsub(strval($wallet->balance), strval($transaction->amount), 2);
                        if (bccomp($newBalance, '0', 2) < 0) {
                            Log::warning('refund_commission_balance_insufficient', [
                                'wallet_id' => $wallet->id,
                                'transaction_id' => $transaction->id,
                                'order_no' => $order->order_no,
                                'current_balance' => $wallet->balance,
                                'deduct_amount' => $transaction->amount,
                                'shortfall' => bcsub(strval($transaction->amount), strval($wallet->balance), 2),
                            ]);
                            $newBalance = '0.00';
                        }
                        $wallet->balance = $newBalance;
                        $wallet->save();

                        $agent = Agent::where('user_id', $wallet->user_id)->lock(true)->find();
                        if ($agent) {
                            $newAvailable = bcsub(strval($agent->available_balance), strval($transaction->amount), 2);
                            if (bccomp($newAvailable, '0', 2) < 0) {
                                $newAvailable = '0.00';
                            }
                            $agent->available_balance = $newAvailable;
                            $agent->total_earnings = bcsub(strval($agent->total_earnings), strval($transaction->amount), 2);
                            if (bccomp(strval($agent->total_earnings), '0', 2) < 0) {
                                $agent->total_earnings = '0.00';
                            }
                            $agent->save();
                        }

                        $refundTransaction = new WalletTransaction();
                        $refundTransaction->wallet_id = $wallet->id;
                        $refundTransaction->type = 2;
                        $refundTransaction->amount = $transaction->amount;
                        $refundTransaction->related_order = $order->order_no;
                        $refundTransaction->balance_after = $wallet->balance;
                        $refundTransaction->remark = '订单退款扣回佣金';
                        $refundTransaction->save();
                    }
                }
            }

            Db::commit();
            return ['success' => true, 'message' => '佣金已扣回'];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('refund_commission_error', [
                'order_id' => $order->id,
                'msg' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
