<template>
  <div class="risk-overview">
    <div class="page-header">
      <h2>风控总览</h2>
    </div>

    <el-row :gutter="20">
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background: #f56c6c">
              <el-icon :size="28"><Warning /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.today_alerts || 0 }}</div>
              <div class="stat-label">今日告警</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background: #e6a23c">
              <el-icon :size="28"><User /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.blocked_ip || 0 }}</div>
              <div class="stat-label">IP黑名单</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background: #909399">
              <el-icon :size="28"><Monitor /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.blocked_device || 0 }}</div>
              <div class="stat-label">设备黑名单</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background: #67c23a">
              <el-icon :size="28"><CircleCheck /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.intercepted || 0 }}</div>
              <div class="stat-label">今日拦截</div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px">
      <el-col :span="12">
        <el-card header="告警分布">
          <div class="alert-distribution">
            <div class="dist-item">
              <div class="dist-header">
                <span class="dist-name">注册异常</span>
                <span class="dist-count">{{ stats.register_abnormal || 0 }}</span>
              </div>
              <div class="dist-bar">
                <div class="dist-bar-inner" :style="{ width: getPercent('register_abnormal') + '%', background: '#f56c6c' }"></div>
              </div>
            </div>
            <div class="dist-item">
              <div class="dist-header">
                <span class="dist-name">订单异常</span>
                <span class="dist-count">{{ stats.order_abnormal || 0 }}</span>
              </div>
              <div class="dist-bar">
                <div class="dist-bar-inner" :style="{ width: getPercent('order_abnormal') + '%', background: '#e6a23c' }"></div>
              </div>
            </div>
            <div class="dist-item">
              <div class="dist-header">
                <span class="dist-name">API异常</span>
                <span class="dist-count">{{ stats.api_abnormal || 0 }}</span>
              </div>
              <div class="dist-bar">
                <div class="dist-bar-inner" :style="{ width: getPercent('api_abnormal') + '%', background: '#409eff' }"></div>
              </div>
            </div>
            <div class="dist-item">
              <div class="dist-header">
                <span class="dist-name">其他告警</span>
                <span class="dist-count">{{ stats.other_alerts || 0 }}</span>
              </div>
              <div class="dist-bar">
                <div class="dist-bar-inner" :style="{ width: getPercent('other_alerts') + '%', background: '#909399' }"></div>
              </div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card header="最近告警">
          <div class="recent-alerts">
            <div v-if="recentAlerts.length === 0" class="empty">
              暂无告警记录
            </div>
            <div v-for="(item, index) in recentAlerts" :key="index" class="alert-item">
              <el-tag :type="getLevelTagType(item.level)" size="small" class="alert-tag">
                {{ getLevelText(item.level) }}
              </el-tag>
              <div class="alert-content">
                <div class="alert-title">{{ item.title }}</div>
                <div class="alert-time">{{ item.created_at }}</div>
              </div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px">
      <el-col :span="8">
        <el-card class="info-card danger">
          <div class="info-content">
            <div class="info-icon" style="background: #fef0f0; color: #f56c6c">
              <el-icon :size="24"><WarnTriangleFilled /></el-icon>
            </div>
            <div class="info-detail">
              <div class="info-value">{{ stats.high_level || 0 }}</div>
              <div class="info-label">高危告警</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card class="info-card warning">
          <div class="info-content">
            <div class="info-icon" style="background: #fdf6ec; color: #e6a23c">
              <el-icon :size="24"><WarningFilled /></el-icon>
            </div>
            <div class="info-detail">
              <div class="info-value">{{ stats.medium_level || 0 }}</div>
              <div class="info-label">中危告警</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card class="info-card info">
          <div class="info-content">
            <div class="info-icon" style="background: #ecf5ff; color: #409eff">
              <el-icon :size="24"><InfoFilled /></el-icon>
            </div>
            <div class="info-detail">
              <div class="info-value">{{ stats.low_level || 0 }}</div>
              <div class="info-label">低危告警</div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import {
  Warning,
  User,
  Monitor,
  CircleCheck,
  WarnTriangleFilled,
  WarningFilled,
  InfoFilled
} from '@element-plus/icons-vue'
import { getRiskOverview } from '@/api/admin/risk'

const loading = ref(false)
const stats = reactive({
  today_alerts: 0,
  blocked_ip: 0,
  blocked_device: 0,
  intercepted: 0,
  register_abnormal: 0,
  order_abnormal: 0,
  api_abnormal: 0,
  other_alerts: 0,
  high_level: 0,
  medium_level: 0,
  low_level: 0
})

const recentAlerts = ref([])

async function fetchOverview() {
  loading.value = true
  try {
    const res = await getRiskOverview()
    if (res.code === 0) {
      Object.assign(stats, res.data)
      recentAlerts.value = res.data.recent_alerts || []
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function getTotalAlerts() {
  return (stats.register_abnormal || 0) +
    (stats.order_abnormal || 0) +
    (stats.api_abnormal || 0) +
    (stats.other_alerts || 0)
}

function getPercent(type) {
  const total = getTotalAlerts()
  if (total === 0) return 0
  return Math.round((stats[type] || 0) / total * 100)
}

function getLevelTagType(level) {
  const map = { 1: 'danger', 2: 'warning', 3: 'info' }
  return map[level] || 'info'
}

function getLevelText(level) {
  const map = { 1: '高危', 2: '中危', 3: '低危' }
  return map[level] || '低危'
}

onMounted(() => {
  fetchOverview()
})
</script>

<style scoped>
.risk-overview {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
}

.stat-card {
  border-radius: 8px;
}

.stat-content {
  display: flex;
  align-items: center;
  gap: 16px;
}

.stat-icon {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
}

.stat-info {
  flex: 1;
}

.stat-value {
  font-size: 22px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 2px;
}

.stat-label {
  font-size: 13px;
  color: #909399;
}

.alert-distribution {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 10px 0;
}

.dist-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.dist-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.dist-name {
  font-size: 13px;
  color: #606266;
}

.dist-count {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
}

.dist-bar {
  height: 8px;
  background: #f0f2f5;
  border-radius: 4px;
  overflow: hidden;
}

.dist-bar-inner {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s;
}

.recent-alerts {
  max-height: 280px;
  overflow-y: auto;
}

.empty {
  text-align: center;
  padding: 40px 0;
  color: #909399;
  font-size: 14px;
}

.alert-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid #f0f2f5;
}

.alert-item:last-child {
  border-bottom: none;
}

.alert-tag {
  flex-shrink: 0;
}

.alert-content {
  flex: 1;
  min-width: 0;
}

.alert-title {
  font-size: 14px;
  color: #303133;
  margin-bottom: 4px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.alert-time {
  font-size: 12px;
  color: #909399;
}

.info-card {
  border-radius: 8px;
}

.info-card.danger {
  border-left: 4px solid #f56c6c;
}

.info-card.warning {
  border-left: 4px solid #e6a23c;
}

.info-card.info {
  border-left: 4px solid #409eff;
}

.info-content {
  display: flex;
  align-items: center;
  gap: 16px;
}

.info-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.info-detail {
  flex: 1;
}

.info-value {
  font-size: 20px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 2px;
}

.info-label {
  font-size: 13px;
  color: #909399;
}
</style>
