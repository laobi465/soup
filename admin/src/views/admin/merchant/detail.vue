<template>
  <div class="merchant-detail">
    <div class="page-header">
      <el-button @click="goBack">
        <el-icon><ArrowLeft /></el-icon>
        返回
      </el-button>
      <h2>商户详情</h2>
    </div>

    <div v-loading="loading" class="detail-content">
      <el-card class="info-card">
        <template #header>
          <div class="card-header">
            <span>基本信息</span>
          </div>
        </template>
        <el-descriptions :column="2" border>
          <el-descriptions-item label="商户ID">{{ detail.id }}</el-descriptions-item>
          <el-descriptions-item label="商户编号">{{ detail.merchant_no }}</el-descriptions-item>
          <el-descriptions-item label="商户名称">{{ detail.merchant_name }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="detail.status === 1 ? 'success' : 'danger'">
              {{ detail.status === 1 ? '正常' : '禁用' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="用户名">{{ detail.user?.username }}</el-descriptions-item>
          <el-descriptions-item label="邮箱">{{ detail.user?.email }}</el-descriptions-item>
          <el-descriptions-item label="手机号">{{ detail.user?.phone || '-' }}</el-descriptions-item>
          <el-descriptions-item label="注册时间">{{ detail.user?.created_at }}</el-descriptions-item>
          <el-descriptions-item label="最后登录">{{ detail.user?.last_login_at || '-' }}</el-descriptions-item>
          <el-descriptions-item label="登录IP">{{ detail.user?.last_login_ip || '-' }}</el-descriptions-item>
        </el-descriptions>
      </el-card>

      <el-card class="info-card">
        <template #header>
          <div class="card-header">
            <span>套餐信息</span>
          </div>
        </template>
        <el-descriptions :column="2" border>
          <el-descriptions-item label="当前套餐">
            {{ detail.package ? detail.package.name : '未开通' }}
          </el-descriptions-item>
          <el-descriptions-item label="套餐到期">
            {{ detail.package_expire || '永久' }}
            <el-tag v-if="detail.is_package_expired" type="danger" style="margin-left: 8px">已过期</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="应用配额">
            {{ detail.app_quota }}
            <span v-if="detail.remaining_apps >= 0" class="muted"> (剩余 {{ detail.remaining_apps }})</span>
            <span v-else class="muted"> (不限)</span>
          </el-descriptions-item>
          <el-descriptions-item label="卡密配额">
            {{ detail.card_quota }}
            <span v-if="detail.remaining_cards >= 0" class="muted"> (剩余 {{ detail.remaining_cards }})</span>
            <span v-else class="muted"> (不限)</span>
          </el-descriptions-item>
        </el-descriptions>
      </el-card>

      <el-card class="info-card">
        <template #header>
          <div class="card-header">
            <span>财务信息</span>
          </div>
        </template>
        <el-descriptions :column="2" border>
          <el-descriptions-item label="余额">¥{{ detail.balance }}</el-descriptions-item>
          <el-descriptions-item label="冻结余额">¥{{ detail.frozen_balance }}</el-descriptions-item>
        </el-descriptions>
      </el-card>

      <el-card class="info-card">
        <template #header>
          <div class="card-header">
            <span>操作日志</span>
          </div>
        </template>
        <el-table :data="detail.operation_logs || []" border stripe size="small">
          <el-table-column prop="id" label="ID" width="80" />
          <el-table-column prop="action" label="操作" width="150" />
          <el-table-column prop="ip" label="IP" width="140" />
          <el-table-column prop="request_data" label="详情">
            <template #default="{ row }">
              <span v-if="row.request_data">{{ formatJson(row.request_data) }}</span>
              <span v-else>-</span>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="时间" width="180" />
        </el-table>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft } from '@element-plus/icons-vue'
import { getMerchantDetail } from '@/api/admin/merchant'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const detail = reactive({
  id: 0,
  merchant_no: '',
  merchant_name: '',
  status: 0,
  package: null,
  package_expire: '',
  is_package_expired: false,
  app_quota: 0,
  card_quota: 0,
  card_used: 0,
  remaining_apps: 0,
  remaining_cards: 0,
  balance: 0,
  frozen_balance: 0,
  user: null,
  operation_logs: []
})

function goBack() {
  router.back()
}

function formatJson(str) {
  try {
    const obj = JSON.parse(str)
    return JSON.stringify(obj)
  } catch (e) {
    return str
  }
}

async function fetchDetail() {
  const id = route.params.id
  if (!id) return
  loading.value = true
  try {
    const res = await getMerchantDetail(id)
    if (res.code === 0) {
      Object.assign(detail, res.data)
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchDetail()
})
</script>

<style scoped>
.merchant-detail {
  padding: 20px;
}

.page-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
}

.detail-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.card-header {
  font-weight: 600;
  font-size: 15px;
}

.muted {
  color: #909399;
  font-size: 13px;
}
</style>
