<template>
  <div class="app-detail" v-loading="loading">
    <div class="page-header">
      <el-button @click="goBack">
        <el-icon><ArrowLeft /></el-icon>
        返回
      </el-button>
      <h2>应用详情</h2>
      <div class="header-actions">
        <el-button
          :type="app?.status === 1 ? 'warning' : 'success'"
          @click="handleToggleStatus"
        >
          {{ app?.status === 1 ? '停用应用' : '启用应用' }}
        </el-button>
        <el-button type="danger" @click="handleDelete">
          <el-icon><Delete /></el-icon>
          删除应用
        </el-button>
      </div>
    </div>

    <el-row :gutter="20">
      <el-col :span="24" :lg="8">
        <el-card class="info-card">
          <template #header>
            <span>基础信息</span>
          </template>
          <div class="app-base">
            <div class="app-icon" v-if="app?.icon">
              <img :src="app.icon" alt="" />
            </div>
            <div class="app-icon default-icon" v-else>
              <el-icon><Monitor /></el-icon>
            </div>
            <div class="app-name">{{ app?.name }}</div>
            <div class="app-version">v{{ app?.version || '-' }}</div>
          </div>
          <el-descriptions :column="1" border>
            <el-descriptions-item label="所属商户">
              {{ app?.merchant_name || '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="AppKey">
              <div class="copy-text">
                <span>{{ app?.app_key }}</span>
                <el-button type="text" @click="copyText(app?.app_key)">
                  <el-icon><CopyDocument /></el-icon>
                </el-button>
              </div>
            </el-descriptions-item>
            <el-descriptions-item label="状态">
              <el-tag :type="app?.status === 1 ? 'success' : 'danger'">
                {{ app?.status === 1 ? '启用' : '停用' }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="绑定上限">
              {{ app?.bind_limit }} 台设备
            </el-descriptions-item>
            <el-descriptions-item label="创建时间">
              {{ app?.created_at }}
            </el-descriptions-item>
            <el-descriptions-item label="更新时间">
              {{ app?.updated_at }}
            </el-descriptions-item>
          </el-descriptions>

          <div class="section">
            <div class="section-title">应用描述</div>
            <div class="description">{{ app?.description || '暂无描述' }}</div>
          </div>

          <div class="section">
            <div class="section-title">IP白名单</div>
            <div class="ip-list" v-if="app?.ip_whitelist?.length">
              <el-tag v-for="(ip, index) in app.ip_whitelist" :key="index" class="ip-tag">
                {{ ip }}
              </el-tag>
            </div>
            <div v-else class="empty-ip">未设置，允许所有IP访问</div>
          </div>
        </el-card>
      </el-col>

      <el-col :span="24" :lg="16">
        <el-card class="stats-card">
          <template #header>
            <span>卡密统计</span>
          </template>
          <el-row :gutter="20">
            <el-col :span="8" v-for="(item, key) in cardStatItems" :key="key">
              <div class="stat-card" :class="key">
                <div class="stat-value">{{ cardStats[key] || 0 }}</div>
                <div class="stat-label">{{ item.label }}</div>
              </div>
            </el-col>
          </el-row>
        </el-card>

        <el-card class="stats-card">
          <template #header>
            <span>API 调用统计</span>
          </template>
          <el-row :gutter="20">
            <el-col :span="8">
              <div class="stat-card today">
                <div class="stat-value">{{ apiStats.today || 0 }}</div>
                <div class="stat-label">今日调用</div>
              </div>
            </el-col>
            <el-col :span="8">
              <div class="stat-card week">
                <div class="stat-value">{{ apiStats.week || 0 }}</div>
                <div class="stat-label">本周调用</div>
              </div>
            </el-col>
            <el-col :span="8">
              <div class="stat-card month">
                <div class="stat-value">{{ apiStats.month || 0 }}</div>
                <div class="stat-label">本月调用</div>
              </div>
            </el-col>
          </el-row>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  ArrowLeft,
  Delete,
  Monitor,
  CopyDocument
} from '@element-plus/icons-vue'
import {
  getApp,
  updateAppStatus,
  deleteApp
} from '@/api/admin/app'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const app = ref(null)

const cardStats = reactive({
  total: 0,
  used: 0,
  unused: 0,
  activated: 0,
  expired: 0,
  banned: 0
})

const apiStats = reactive({
  today: 0,
  week: 0,
  month: 0
})

const cardStatItems = {
  total: { label: '总卡密数' },
  unused: { label: '未使用' },
  activated: { label: '已激活' },
  expired: { label: '已到期' },
  banned: { label: '已封禁' },
  used: { label: '已使用' }
}

const appId = route.params.id

async function fetchDetail() {
  loading.value = true
  try {
    const res = await getApp(appId)
    if (res.code === 0) {
      app.value = res.data
      if (res.data.card_stats) {
        Object.assign(cardStats, res.data.card_stats)
      }
      if (res.data.api_stats) {
        Object.assign(apiStats, res.data.api_stats)
      }
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

function handleToggleStatus() {
  if (!app.value) return
  const action = app.value.status === 1 ? '停用' : '启用'
  ElMessageBox.confirm(
    `确定要${action}应用「${app.value.name}」吗？`,
    '提示',
    {
      type: 'warning',
      confirmButtonText: '确定',
      cancelButtonText: '取消'
    }
  ).then(async () => {
    try {
      const res = await updateAppStatus(appId, app.value.status === 1 ? 0 : 1)
      if (res.code === 0) {
        ElMessage.success(`${action}成功`)
        fetchDetail()
      }
    } catch (e) {
      console.error(e)
    }
  }).catch(() => {})
}

function handleDelete() {
  if (!app.value) return
  ElMessageBox.confirm(
    `确定要删除应用「${app.value.name}」吗？删除后将无法恢复。`,
    '警告',
    {
      type: 'warning',
      confirmButtonText: '确定',
      cancelButtonText: '取消'
    }
  ).then(async () => {
    try {
      const res = await deleteApp(appId)
      if (res.code === 0) {
        ElMessage.success('删除成功')
        router.back()
      }
    } catch (e) {
      console.error(e)
    }
  }).catch(() => {})
}

function copyText(text) {
  if (!text) return
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
.app-detail {
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
  flex: 1;
}

.header-actions {
  display: flex;
  gap: 10px;
}

.info-card {
  margin-bottom: 20px;
}

.app-base {
  text-align: center;
  padding: 20px 0;
  border-bottom: 1px solid #ebeef5;
  margin-bottom: 20px;
}

.app-icon {
  width: 80px;
  height: 80px;
  border-radius: 12px;
  overflow: hidden;
  margin: 0 auto 12px;
  background: #f5f7fa;
  display: flex;
  align-items: center;
  justify-content: center;
}

.app-icon img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.app-icon.default-icon {
  font-size: 40px;
  color: #909399;
}

.app-name {
  font-size: 20px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 4px;
}

.app-version {
  font-size: 14px;
  color: #909399;
}

.copy-text {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: monospace;
  word-break: break-all;
}

.section {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #ebeef5;
}

.section-title {
  font-size: 14px;
  font-weight: 500;
  color: #303133;
  margin-bottom: 12px;
}

.description {
  color: #606266;
  font-size: 14px;
  line-height: 1.6;
  white-space: pre-wrap;
}

.ip-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.ip-tag {
  font-family: monospace;
}

.empty-ip {
  color: #909399;
  font-size: 14px;
}

.stats-card {
  margin-bottom: 20px;
}

.stat-card {
  text-align: center;
  padding: 20px;
  border-radius: 8px;
  background: #f5f7fa;
}

.stat-card .stat-value {
  font-size: 32px;
  font-weight: 600;
  margin-bottom: 8px;
  color: #303133;
}

.stat-card .stat-label {
  font-size: 14px;
  color: #909399;
}

.stat-card.total .stat-value {
  color: #409eff;
}

.stat-card.unused .stat-value {
  color: #67c23a;
}

.stat-card.activated .stat-value {
  color: #e6a23c;
}

.stat-card.expired .stat-value {
  color: #909399;
}

.stat-card.banned .stat-value {
  color: #f56c6c;
}

.stat-card.used .stat-value {
  color: #909399;
}

.stat-card.today .stat-value {
  color: #409eff;
}

.stat-card.week .stat-value {
  color: #67c23a;
}

.stat-card.month .stat-value {
  color: #e6a23c;
}
</style>
