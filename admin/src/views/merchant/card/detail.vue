<template>
  <div class="card-detail">
    <div class="page-header">
      <el-button @click="goBack">
        <el-icon><ArrowLeft /></el-icon>
        返回
      </el-button>
      <h2>卡密详情</h2>
    </div>

    <el-row :gutter="20">
      <el-col :span="14">
        <el-card v-loading="loading" class="detail-card">
          <template #header>
            <div class="card-header">
              <span>基本信息</span>
              <div class="header-actions">
                <el-button
                  v-if="card && card.status !== 4 && card.status !== 5"
                  type="warning"
                  size="small"
                  @click="handleBan"
                >
                  封禁
                </el-button>
                <el-button
                  v-if="card && card.status === 4"
                  type="success"
                  size="small"
                  @click="handleUnban"
                >
                  解封
                </el-button>
                <el-button
                  v-if="card && card.status !== 5"
                  type="danger"
                  size="small"
                  @click="handleVoid"
                >
                  作废
                </el-button>
                <el-button
                  v-if="card && card.card_type !== 6 && card.status !== 5"
                  type="primary"
                  size="small"
                  @click="handleRenew"
                >
                  续费
                </el-button>
              </div>
            </div>
          </template>

          <el-descriptions :column="2" border v-if="card">
            <el-descriptions-item label="ID">{{ card.id }}</el-descriptions-item>
            <el-descriptions-item label="卡密前缀">
              <span class="prefix-text">{{ card.card_no_prefix || '-' }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="应用">{{ card.app?.name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="卡密类型">
              <el-tag size="small" type="info">{{ card.card_type_text }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="状态">
              <el-tag :type="getStatusTagType(card.status)" size="small">
                {{ card.status_text }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="绑定设备数">
              {{ card.device_count || 0 }} / {{ card.app?.bind_limit || 1 }}
            </el-descriptions-item>
            <el-descriptions-item label="创建时间">{{ card.created_at }}</el-descriptions-item>
            <el-descriptions-item label="激活时间">{{ card.activate_time || '-' }}</el-descriptions-item>
            <el-descriptions-item label="到期时间">
              <span :class="{ 'text-danger': isExpired }">{{ card.expire_time || '永久' }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="软到期时间">
              {{ card.soft_expire_until || '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="总时长" :span="2">
              {{ formatDuration(card.duration) }}
            </el-descriptions-item>
          </el-descriptions>
        </el-card>

        <el-card v-loading="loading" class="devices-card">
          <template #header>
            <span>绑定设备列表</span>
          </template>

          <el-table :data="devices" border empty-text="暂无绑定设备">
            <el-table-column prop="id" label="ID" width="80" />
            <el-table-column prop="device_name" label="设备名称" min-width="150">
              <template #default="{ row }">
                {{ row.device_name || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="device_fingerprint" label="设备指纹" min-width="200">
              <template #default="{ row }">
                <div class="fingerprint">
                  <span>{{ row.device_fingerprint }}</span>
                  <el-button type="text" @click="copyText(row.device_fingerprint)">
                    <el-icon><CopyDocument /></el-icon>
                  </el-button>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="80" align="center">
              <template #default="{ row }">
                <el-tag :type="row.is_online === 1 ? 'success' : 'info'" size="small">
                  {{ row.is_online === 1 ? '在线' : '离线' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="bind_time" label="绑定时间" width="170" />
            <el-table-column prop="last_heartbeat" label="最后心跳" width="170" />
            <el-table-column label="操作" width="100" fixed="right">
              <template #default="{ row }">
                <el-button
                  type="danger"
                  link
                  size="small"
                  @click="handleUnbindDevice(row)"
                >
                  解绑
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>

      <el-col :span="10">
        <el-card class="info-card">
          <template #header>
            <span>操作记录</span>
          </template>
          <el-empty description="暂未实现操作日志" />
        </el-card>
      </el-col>
    </el-row>

    <el-dialog v-model="renewDialogVisible" title="卡密续费" width="400px">
      <el-form :model="renewForm" label-width="100px">
        <el-form-item label="续费时长">
          <el-input-number v-model="renewForm.days" :min="1" :max="3650" />
          <span style="margin-left: 8px;">天</span>
        </el-form-item>
        <el-form-item label="合计">
          <span class="text-primary">{{ renewForm.days }} 天</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="renewDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="renewLoading" @click="submitRenew">确定续费</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowLeft, CopyDocument } from '@element-plus/icons-vue'
import {
  getCard,
  banCard,
  unbanCard,
  voidCard,
  renewCard,
  unbindDevice
} from '@/api/merchant/card'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const renewLoading = ref(false)
const card = ref(null)
const devices = ref([])
const renewDialogVisible = ref(false)

const renewForm = reactive({
  days: 30
})

const isExpired = computed(() => {
  if (!card.value || !card.value.expire_time) return false
  return new Date(card.value.expire_time).getTime() < Date.now()
})

function getStatusTagType(status) {
  const types = {
    1: 'info',
    2: 'success',
    3: 'warning',
    4: 'danger',
    5: 'info'
  }
  return types[status] || 'info'
}

function formatDuration(seconds) {
  if (!seconds) return '-'
  const days = Math.floor(seconds / 86400)
  const hours = Math.floor((seconds % 86400) / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  if (days > 0) {
    return `${days} 天 ${hours} 小时`
  }
  if (hours > 0) {
    return `${hours} 小时 ${minutes} 分钟`
  }
  return `${minutes} 分钟`
}

async function fetchDetail() {
  const id = route.params.id
  if (!id) return

  loading.value = true
  try {
    const res = await getCard(id)
    if (res.code === 0) {
      card.value = res.data
      devices.value = res.data.devices || []
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.back()
}

function handleBan() {
  ElMessageBox.prompt('请输入封禁原因（可选）', '封禁卡密', {
    confirmButtonText: '确定封禁',
    cancelButtonText: '取消',
    inputPlaceholder: '请输入封禁原因',
    type: 'warning'
  }).then(async ({ value }) => {
    try {
      const res = await banCard(card.value.id, value || '')
      if (res.code === 0) {
        ElMessage.success('封禁成功')
        fetchDetail()
      }
    } catch (e) {
      console.error(e)
    }
  }).catch(() => {})
}

function handleUnban() {
  ElMessageBox.confirm(
    '确定要解封该卡密吗？',
    '提示',
    {
      type: 'warning',
      confirmButtonText: '确定',
      cancelButtonText: '取消'
    }
  ).then(async () => {
    try {
      const res = await unbanCard(card.value.id)
      if (res.code === 0) {
        ElMessage.success('解封成功')
        fetchDetail()
      }
    } catch (e) {
      console.error(e)
    }
  }).catch(() => {})
}

function handleVoid() {
  ElMessageBox.confirm(
    '确定要作废该卡密吗？作废后无法恢复！',
    '警告',
    {
      type: 'warning',
      confirmButtonText: '确定作废',
      cancelButtonText: '取消',
      confirmButtonClass: 'el-button--danger'
    }
  ).then(async () => {
    try {
      const res = await voidCard(card.value.id)
      if (res.code === 0) {
        ElMessage.success('作废成功')
        fetchDetail()
      }
    } catch (e) {
      console.error(e)
    }
  }).catch(() => {})
}

function handleRenew() {
  renewForm.days = 30
  renewDialogVisible.value = true
}

async function submitRenew() {
  renewLoading.value = true
  try {
    const duration = renewForm.days * 86400
    const res = await renewCard(card.value.id, duration)
    if (res.code === 0) {
      ElMessage.success('续费成功')
      renewDialogVisible.value = false
      fetchDetail()
    }
  } catch (e) {
    console.error(e)
  } finally {
    renewLoading.value = false
  }
}

function handleUnbindDevice(row) {
  ElMessageBox.confirm(
    `确定要解绑设备「${row.device_name || row.device_fingerprint}」吗？`,
    '提示',
    {
      type: 'warning',
      confirmButtonText: '确定解绑',
      cancelButtonText: '取消'
    }
  ).then(async () => {
    try {
      const res = await unbindDevice(card.value.id, row.id)
      if (res.code === 0) {
        ElMessage.success('解绑成功')
        fetchDetail()
      }
    } catch (e) {
      console.error(e)
    }
  }).catch(() => {})
}

function copyText(text) {
  navigator.clipboard.writeText(text).then(() => {
    ElMessage.success('已复制到剪贴板')
  }).catch(() => {
    ElMessage.error('复制失败')
  })
}

onMounted(() => {
  fetchDetail()
})
</script>

<style scoped>
.card-detail {
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

.detail-card {
  margin-bottom: 16px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.header-actions {
  display: flex;
  gap: 8px;
}

.prefix-text {
  font-family: monospace;
  font-weight: 600;
  color: #409eff;
}

.text-danger {
  color: #f56c6c;
}

.text-primary {
  color: #409eff;
  font-weight: 600;
}

.devices-card {
  margin-bottom: 16px;
}

.fingerprint {
  display: flex;
  align-items: center;
  gap: 6px;
  font-family: monospace;
  font-size: 12px;
  word-break: break-all;
}

.info-card {
  margin-bottom: 16px;
}
</style>
