<?php
declare (strict_types = 1);

namespace app\service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Db;

class ExportService
{
    public static function exportCards(array $filters = []): array
    {
        $query = Db::name('cards')
            ->alias('c')
            ->join('apps a', 'c.app_id = a.id')
            ->field([
                'c.id',
                'a.name as app_name',
                'c.card_no_prefix',
                'c.card_type',
                'c.status',
                'c.duration',
                'c.created_at',
                'c.activate_time',
                'c.expire_time',
            ]);

        if (!empty($filters['merchant_id'])) {
            $query->where('c.merchant_id', intval($filters['merchant_id']));
        }
        if (!empty($filters['app_id'])) {
            $query->where('c.app_id', intval($filters['app_id']));
        }
        if (!empty($filters['status'])) {
            $query->where('c.status', intval($filters['status']));
        }
        if (!empty($filters['card_type'])) {
            $query->where('c.card_type', intval($filters['card_type']));
        }
        if (!empty($filters['start_time'])) {
            $query->where('c.created_at', '>=', $filters['start_time']);
        }
        if (!empty($filters['end_time'])) {
            $query->where('c.created_at', '<=', $filters['end_time']);
        }

        $list = $query->order('c.id', 'desc')->limit(10000)->select()->toArray();

        $cardTypes = [
            1 => '日卡',
            2 => '周卡',
            3 => '月卡',
            4 => '季卡',
            5 => '年卡',
            6 => '永久卡',
            7 => '试用卡',
        ];
        $statuses = [
            1 => '未使用',
            2 => '已激活',
            3 => '已到期',
            4 => '已封禁',
            5 => '已作废',
            6 => '已售出',
        ];

        foreach ($list as &$item) {
            $item['card_type_text'] = $cardTypes[$item['card_type']] ?? '未知';
            $item['status_text'] = $statuses[$item['status']] ?? '未知';
            $item['duration_text'] = $item['duration'] ? ($item['duration'] / 86400) . '天' : '永久';
        }
        unset($item);

        $headers = ['ID', '应用名称', '卡密前缀', '卡密类型', '状态', '时长', '创建时间', '激活时间', '到期时间'];
        $columns = ['id', 'app_name', 'card_no_prefix', 'card_type_text', 'status_text', 'duration_text', 'created_at', 'activate_time', 'expire_time'];

        $filePath = self::generateExcel('卡密数据', $headers, $columns, $list);

        return [
            'file_path' => $filePath,
            'file_name' => 'cards_' . date('YmdHis') . '.xlsx',
            'total' => count($list),
        ];
    }

    public static function exportOrders(array $filters = []): array
    {
        $query = Db::name('orders')
            ->alias('o')
            ->leftJoin('merchants m', 'o.merchant_id = m.id')
            ->field([
                'o.id',
                'o.order_no',
                'm.merchant_name',
                'o.type',
                'o.pay_status',
                'o.pay_channel',
                'o.pay_amount',
                'o.platform_fee',
                'o.created_at',
                'o.paid_at',
            ]);

        if (!empty($filters['merchant_id'])) {
            $query->where('o.merchant_id', intval($filters['merchant_id']));
        }
        if (!empty($filters['status'])) {
            $query->where('o.pay_status', intval($filters['status']));
        }
        if (!empty($filters['type'])) {
            $query->where('o.type', intval($filters['type']));
        }
        if (!empty($filters['start_time'])) {
            $query->where('o.created_at', '>=', $filters['start_time']);
        }
        if (!empty($filters['end_time'])) {
            $query->where('o.created_at', '<=', $filters['end_time']);
        }

        $list = $query->order('o.id', 'desc')->limit(10000)->select()->toArray();

        $types = [
            1 => '套餐购买',
            2 => '发卡商品',
            3 => '余额充值',
            4 => '套餐续费',
        ];
        $statuses = [
            1 => '待支付',
            2 => '已支付',
            3 => '已关闭',
            4 => '已退款',
        ];
        $channels = [
            'alipay' => '支付宝',
            'wxpay' => '微信支付',
            'qqpay' => 'QQ钱包',
            'balance' => '余额支付',
        ];

        foreach ($list as &$item) {
            $item['type_text'] = $types[$item['type']] ?? '未知';
            $item['status_text'] = $statuses[$item['pay_status']] ?? '未知';
            $item['channel_text'] = $channels[$item['pay_channel']] ?? ($item['pay_channel'] ?? '未知');
        }
        unset($item);

        $headers = ['ID', '订单号', '商户名称', '订单类型', '支付状态', '支付方式', '支付金额', '平台手续费', '创建时间', '支付时间'];
        $columns = ['id', 'order_no', 'merchant_name', 'type_text', 'status_text', 'channel_text', 'pay_amount', 'platform_fee', 'created_at', 'paid_at'];

        $filePath = self::generateExcel('订单数据', $headers, $columns, $list);

        return [
            'file_path' => $filePath,
            'file_name' => 'orders_' . date('YmdHis') . '.xlsx',
            'total' => count($list),
        ];
    }

    public static function exportCommissions(array $filters = []): array
    {
        $query = Db::name('wallet_transactions')
            ->alias('t')
            ->leftJoin('agents a', 't.user_id = a.user_id')
            ->field([
                't.id',
                'a.name as agent_name',
                'a.phone as agent_phone',
                't.type',
                't.amount',
                't.balance_after',
                't.remark',
                't.created_at',
            ]);

        $query->where('t.type', 'commission');

        if (!empty($filters['agent_id'])) {
            $agent = Db::name('agents')->where('id', intval($filters['agent_id']))->find();
            if ($agent) {
                $query->where('t.user_id', $agent['user_id']);
            }
        }
        if (!empty($filters['start_time'])) {
            $query->where('t.created_at', '>=', $filters['start_time']);
        }
        if (!empty($filters['end_time'])) {
            $query->where('t.created_at', '<=', $filters['end_time']);
        }

        $list = $query->order('t.id', 'desc')->limit(10000)->select()->toArray();

        $headers = ['ID', '代理名称', '代理手机号', '交易类型', '金额', '变动后余额', '备注', '创建时间'];
        $columns = ['id', 'agent_name', 'agent_phone', 'type', 'amount', 'balance_after', 'remark', 'created_at'];

        $filePath = self::generateExcel('佣金记录', $headers, $columns, $list);

        return [
            'file_path' => $filePath,
            'file_name' => 'commissions_' . date('YmdHis') . '.xlsx',
            'total' => count($list),
        ];
    }

    public static function exportLogs(array $filters = []): array
    {
        $query = Db::name('operation_logs')
            ->alias('l')
            ->leftJoin('users u', 'l.user_id = u.id')
            ->field([
                'l.id',
                'u.username',
                'l.action',
                'l.target_type',
                'l.target_id',
                'l.ip',
                'l.user_agent',
                'l.request_data',
                'l.created_at',
            ]);

        if (!empty($filters['user_id'])) {
            $query->where('l.user_id', intval($filters['user_id']));
        }
        if (!empty($filters['action'])) {
            $query->where('l.action', 'like', '%' . $filters['action'] . '%');
        }
        if (!empty($filters['start_time'])) {
            $query->where('l.created_at', '>=', $filters['start_time']);
        }
        if (!empty($filters['end_time'])) {
            $query->where('l.created_at', '<=', $filters['end_time']);
        }

        $list = $query->order('l.id', 'desc')->limit(10000)->select()->toArray();

        $headers = ['ID', '用户名', '操作', '目标类型', '目标ID', 'IP地址', 'User Agent', '请求数据', '操作时间'];
        $columns = ['id', 'username', 'action', 'target_type', 'target_id', 'ip', 'user_agent', 'request_data', 'created_at'];

        $filePath = self::generateExcel('操作日志', $headers, $columns, $list);

        return [
            'file_path' => $filePath,
            'file_name' => 'logs_' . date('YmdHis') . '.xlsx',
            'total' => count($list),
        ];
    }

    protected static function generateExcel(string $sheetName, array $headers, array $columns, array $data): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $col++;
        }

        $row = 2;
        foreach ($data as $item) {
            $col = 'A';
            foreach ($columns as $column) {
                $value = $item[$column] ?? '';
                if (is_string($value) && strlen($value) > 100) {
                    $value = mb_substr($value, 0, 100, 'UTF-8') . '...';
                }
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        $dir = runtime_path() . 'export/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fileName = 'export_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.xlsx';
        $filePath = $dir . $fileName;

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }

    public static function downloadFile(string $filePath, string $fileName): void
    {
        if (!file_exists($filePath)) {
            throw new \Exception('文件不存在');
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        readfile($filePath);
        exit;
    }
}
