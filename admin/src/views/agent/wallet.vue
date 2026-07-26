<template>
  <div class="agent-wallet">
    <div class="page-header">
      <h2>我的钱包</h2>
    </div>

    <div v-loading="loading" class="wallet-content">
      <el-row :gutter="20">
        <el-col :span="8">
          <el-card class="balance-card">
            <div class="balance-icon">
              <el-icon :size="32"><Wallet /></el-icon>
            </div>
            <div class="balance-info">
              <div class="balance-label">可用余额</div>
              <div class="balance-value">¥{{ wallet?.available || '0.00' }}</div>
            </div>
          </el-card>
        </el-col>
        <el-col :span="8">
          <el-card class="balance-card frozen">
            <div class="balance-icon">
              <el-icon :size="32"><Lock /></el-icon>
            </div>
            <div class="balance-info">
              <div class="balance-label">冻结余额</div>
              <div class="balance-value">¥{{ wallet?.frozen || '0.00' }}</div>
            </div>
          </el-card>
        </el-col>
        <el-col :span="8">
          <el-card class="balance-card total">
            <div class="balance-icon">
              <el-icon :size="32"><Money /></el-icon>
            </div>
            <div class="balance-info">
              <div class="balance-label">累计收益</div>
              <div class="balance-value">¥{{ wallet?.total_income || '0.00' }}</div>
            </div>
          </el-card>
        </el-col>
      </el-row>

      <el-card style="margin-top: 20px;">
        <template #header>
          <span>提现申请</span>
        </template>
        <el-form :model="withdrawForm" :rules="withdrawRules" ref="withdrawFormRef" label-width="100px" style="max-width: 500px;">
          <el-form-item label="提现金额">
            <el-input-number v-model="withdrawForm.amount" :min="1" :max="Number(wallet?.available || 0)" :precision="2" style="width: 200px;" />
            <span class="tip">最低提现1元，手续费3%</span>
          </el-form-item>
          <el-form-item label="收款方式">
            <el-radio-group v-model="withdrawForm.pay_type">
              <el-radio value="alipay">支付宝</el-radio>
              <el-radio value="wechat">微信</el-radio>
              <el-radio value="bank">银行卡</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="收款账号">
            <el-input v-model="withdrawForm.account" placeholder="请输入收款账号" />
          </el-form-item>
          <el-form-item label="真实姓名">
            <el-input v-model="withdrawForm.name" placeholder="请输入真实姓名" />
          </el-form-item>
          <el-form-item v-if="withdrawForm.pay_type === 'bank'" label="开户行">
            <el-input v-model="withdrawForm.bank_name" placeholder="请输入开户行" />
          </el-form-item>
          <el-form-item label="预计到账">
            <span class="estimate">¥{{ calculateEstimate() }}</span>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="submitWithdraw" :loading="withdrawLoading">申请提现</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <el-card style="margin-top: 20px;">
        <template #header>
          <span>提现记录</span>
        </template>
        <el-table :data="withdrawList" v-loading="withdrawListLoading" border stripe>
          <el-table-column prop="id" label="ID" width="80" />
          <el-table-column prop="amount" label="金额" width="120">
            <template #default="{ row }">
              ¥{{ row.amount }}
            </template>
          </el-table-column>
          <el-table-column prop="fee" label="手续费" width="100">
            <template #default="{ row }">
              ¥{{ row.fee }}
            </template>
          </el-table-column>
          <el-table-column prop="pay_type_text" label="方式" width="100" />
          <el-table-column prop="status_text" label="状态" width="100">
            <template #default="{ row }">
              <el-tag size="small" :type="getStatusType(row.status)">
                {{ row.status_text }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="申请时间" width="180" />
          <el-table-column prop="audit_time" label="审核时间" width="180">
            <template #default="{ row }">
              {{ row.audit_time || '-' }}
            </template>
          </el-table-column>
        </el-table>

        <div class="pagination">
          <el-pagination
            v-model:current-page="pagination.page"
            v-model:page-size="pagination.pageSize"
            :total="pagination.total"
            :page-sizes="[10, 20, 50]"
            layout="total, sizes, prev, pager, next, jumper"
            @size-change="fetchWithdrawList"
            @current-change="fetchWithdrawList"
          />
        </div>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Wallet, Lock, Money } from '@element-plus/icons-vue'
import { getAgentWallet, getWithdrawList, applyWithdraw } from '@/api/agent'

const loading = ref(false)
const withdrawLoading = ref(false)
const withdrawListLoading = ref(false)
const wallet = ref(null)
const withdrawList = ref([])
const withdrawFormRef = ref(null)

const withdrawForm = reactive({
  amount: 0,
  pay_type: 'alipay',
  account: '',
  name: '',
  bank_name: ''
})

const withdrawRules = {
  amount: [{ required: true, message: '请输入提现金额', trigger: 'blur' }],
  account: [{ required: true, message: '请输入收款账号', trigger: 'blur' }],
  name: [{ required: true, message: '请输入真实姓名', trigger: 'blur' }]
}

const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

function getStatusType(status) {
  const map = {
    1: 'warning',
    2: 'success',
    3: 'danger',
    4: 'info'
  }
  return map[status] || 'info'
}

function calculateEstimate() {
  if (!withdrawForm.amount) return '0.00'
  const fee = withdrawForm.amount * 0.03
  return (withdrawForm.amount - fee).toFixed(2)
}

async function fetchWallet() {
  loading.value = true
  try {
    const res = await getAgentWallet()
    if (res.code === 0) {
      wallet.value = res.data
    }
  } finally {
    loading.value = false
  }
}

async function fetchWithdrawList() {
  withdrawListLoading.value = true
  try {
    const res = await getWithdrawList({
      page: pagination.page,
      pageSize: pagination.pageSize
    })
    if (res.code === 0) {
      withdrawList.value = res.data.list
      pagination.total = res.data.total
    }
  } finally {
    withdrawListLoading.value = false
  }
}

async function submitWithdraw() {
  if (!withdrawFormRef.value) return
  await withdrawFormRef.value.validate(async (valid) => {
    if (!valid) return

    ElMessageBox.confirm(
      `确认申请提现 ¥${withdrawForm.amount}？\n手续费3%，实际到账 ¥${calculateEstimate()}`,
      '提现确认',
      {
        type: 'warning',
        confirmButtonText: '确认提现',
        cancelButtonText: '取消'
      }
    ).then(async () => {
      withdrawLoading.value = true
      try {
        const res = await applyWithdraw(withdrawForm)
        if (res.code === 0) {
          ElMessage.success('提现申请已提交')
          withdrawForm.amount = 0
          withdrawForm.account = ''
          withdrawForm.name = ''
          withdrawForm.bank_name = ''
          fetchWallet()
          fetchWithdrawList()
        }
      } finally {
        withdrawLoading.value = false
      }
    }).catch(() => {})
  })
}

onMounted(() => {
  fetchWallet()
  fetchWithdrawList()
})
</script>

<style scoped>
.agent-wallet {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
}

.balance-card {
  display: flex;
  align-items: center;
  gap: 16px;
  border-left: 4px solid #409eff;
}

.balance-card.frozen {
  border-left-color: #e6a23c;
}

.balance-card.total {
  border-left-color: #67c23a;
}

.balance-icon {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  background: #ecf5ff;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #409eff;
}

.balance-card.frozen .balance-icon {
  background: #fdf6ec;
  color: #e6a23c;
}

.balance-card.total .balance-icon {
  background: #f0f9eb;
  color: #67c23a;
}

.balance-label {
  font-size: 14px;
  color: #909399;
  margin-bottom: 4px;
}

.balance-value {
  font-size: 26px;
  font-weight: 600;
  color: #303133;
}

.tip {
  color: #909399;
  font-size: 12px;
  margin-left: 12px;
}

.estimate {
  font-size: 18px;
  font-weight: 600;
  color: #67c23a;
}

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>
