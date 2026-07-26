<template>
  <div class="merchant-wallet">
    <div class="page-header">
      <h2>钱包管理</h2>
    </div>

    <div v-loading="loading" class="wallet-content">
      <el-row :gutter="20">
        <el-col :span="8">
          <el-card class="balance-card">
            <template #header>
              <div class="card-header">
                <span>账户余额</span>
              </div>
            </template>
            <div class="balance-info">
              <div class="balance-amount">
                <span class="symbol">¥</span>
                <span class="amount">{{ walletInfo.wallet?.balance || '0.00' }}</span>
              </div>
              <div class="balance-detail">
                <div class="detail-item">
                  <span class="label">可用余额</span>
                  <span class="value">¥{{ walletInfo.wallet?.available || '0.00' }}</span>
                </div>
                <div class="detail-item">
                  <span class="label">冻结金额</span>
                  <span class="value">¥{{ walletInfo.wallet?.frozen || '0.00' }}</span>
                </div>
              </div>
              <div class="balance-actions">
                <el-button type="primary" size="large" @click="handleRecharge">
                  <el-icon><Plus /></el-icon>
                  余额充值
                </el-button>
              </div>
            </div>
          </el-card>
        </el-col>

        <el-col :span="16">
          <el-card class="transactions-card">
            <template #header>
              <div class="card-header">
                <span>钱包流水</span>
                <div class="filter-bar">
                  <el-select v-model="filterType" placeholder="全部类型" clearable style="width: 140px" @change="fetchTransactions">
                    <el-option label="收入" :value="1" />
                    <el-option label="支出" :value="2" />
                    <el-option label="提现" :value="3" />
                    <el-option label="冻结" :value="4" />
                    <el-option label="解冻" :value="5" />
                  </el-select>
                </div>
              </div>
            </template>
            <el-table :data="transactions" border stripe>
              <el-table-column prop="id" label="ID" width="80" />
              <el-table-column prop="type_text" label="类型" width="100">
                <template #default="{ row }">
                  <el-tag :type="getTypeTagType(row.type)" size="small">{{ row.type_text }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="amount" label="变动金额" width="120">
                <template #default="{ row }">
                  <span :class="['amount', row.type === 1 || row.type === 5 ? 'income' : 'expense']">
                    {{ row.type === 1 || row.type === 5 ? '+' : '-' }}¥{{ row.amount }}
                  </span>
                </template>
              </el-table-column>
              <el-table-column prop="balance_after" label="变动后余额" width="120" />
              <el-table-column prop="related_order" label="关联订单" width="160" />
              <el-table-column prop="remark" label="备注" min-width="150" />
              <el-table-column prop="created_at" label="时间" width="180" />
            </el-table>
            <div class="pagination">
              <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.pageSize"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="fetchTransactions"
                @current-change="fetchTransactions"
              />
            </div>
          </el-card>
        </el-col>
      </el-row>
    </div>

    <el-dialog v-model="rechargeVisible" title="余额充值" width="400px">
      <el-form :model="rechargeForm" :rules="rechargeRules" ref="rechargeFormRef" label-width="80px">
        <el-form-item label="充值金额" prop="amount">
          <el-input-number
            v-model="rechargeForm.amount"
            :min="1"
            :precision="2"
            :step="10"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="支付方式" prop="pay_type">
          <el-radio-group v-model="rechargeForm.pay_type">
            <el-radio value="balance">余额支付</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="rechargeVisible = false">取消</el-button>
        <el-button type="primary" @click="submitRecharge" :loading="rechargeLoading">确认充值</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { getWallet, getTransactions, recharge } from '@/api/merchant/wallet'

const loading = ref(false)
const rechargeVisible = ref(false)
const rechargeLoading = ref(false)
const rechargeFormRef = ref(null)
const filterType = ref('')

const walletInfo = reactive({
  wallet: null,
  merchant_balance: 0
})

const transactions = ref([])
const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

const rechargeForm = reactive({
  amount: 100,
  pay_type: 'balance'
})

const rechargeRules = {
  amount: [{ required: true, message: '请输入充值金额', trigger: 'blur' }],
  pay_type: [{ required: true, message: '请选择支付方式', trigger: 'change' }]
}

function getTypeTagType(type) {
  const map = {
    1: 'success',
    2: 'danger',
    3: 'warning',
    4: 'info',
    5: 'success'
  }
  return map[type] || 'info'
}

async function fetchWallet() {
  try {
    const res = await getWallet()
    if (res.code === 0) {
      walletInfo.wallet = res.data.wallet
      walletInfo.merchant_balance = res.data.merchant_balance
    }
  } catch (e) {
    console.error(e)
  }
}

async function fetchTransactions() {
  loading.value = true
  try {
    const res = await getTransactions({
      page: pagination.page,
      pageSize: pagination.pageSize,
      type: filterType.value
    })
    if (res.code === 0) {
      transactions.value = res.data.list
      pagination.total = res.data.total
    }
  } finally {
    loading.value = false
  }
}

function handleRecharge() {
  rechargeForm.amount = 100
  rechargeForm.pay_type = 'balance'
  rechargeVisible.value = true
}

async function submitRecharge() {
  if (!rechargeFormRef.value) return
  await rechargeFormRef.value.validate(async (valid) => {
    if (!valid) return
    rechargeLoading.value = true
    try {
      const res = await recharge(rechargeForm)
      if (res.code === 0) {
        ElMessage.success('充值订单创建成功，请完成支付')
        rechargeVisible.value = false
        fetchWallet()
        fetchTransactions()
      }
    } finally {
      rechargeLoading.value = false
    }
  })
}

onMounted(() => {
  fetchWallet()
  fetchTransactions()
})
</script>

<style scoped>
.merchant-wallet {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
}

.wallet-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: 600;
}

.balance-card {
  text-align: center;
}

.balance-amount {
  color: #e6a23c;
  margin: 20px 0;
}

.balance-amount .symbol {
  font-size: 18px;
}

.balance-amount .amount {
  font-size: 48px;
  font-weight: 600;
}

.balance-detail {
  display: flex;
  justify-content: space-around;
  padding: 20px 0;
  border-top: 1px solid #ebeef5;
  border-bottom: 1px solid #ebeef5;
  margin-bottom: 20px;
}

.detail-item .label {
  display: block;
  color: #909399;
  font-size: 13px;
  margin-bottom: 4px;
}

.detail-item .value {
  font-size: 16px;
  font-weight: 600;
  color: #303133;
}

.balance-actions {
  padding-top: 10px;
}

.transactions-card {
  min-height: 500px;
}

.filter-bar {
  display: flex;
  gap: 12px;
}

.amount.income {
  color: #67c23a;
}

.amount.expense {
  color: #f56c6c;
}

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>
