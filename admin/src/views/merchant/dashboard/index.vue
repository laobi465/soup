<template>
  <div class="merchant-dashboard">
    <div class="page-header">
      <h2>数据概览</h2>
    </div>

    <el-row :gutter="20">
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background: #409eff">
              <el-icon :size="28"><Monitor /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.app_count || 0 }}</div>
              <div class="stat-label">应用数量</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background: #67c23a">
              <el-icon :size="28"><Key /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">
                {{ (stats.card_stats?.unused || 0) + (stats.card_stats?.activated || 0) + (stats.card_stats?.expired || 0) + (stats.card_stats?.banned || 0) }}
              </div>
              <div class="stat-label">卡密总数</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background: #e6a23c">
              <el-icon :size="28"><Wallet /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">¥{{ stats.card_income || '0.00' }}</div>
              <div class="stat-label">发卡收入</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background: #f56c6c">
              <el-icon :size="28"><Cpu /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.online_devices || 0 }}</div>
              <div class="stat-label">在线设备</div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px">
      <el-col :span="12">
        <el-card header="卡密状态分布">
          <div class="card-stats">
            <div class="stat-item">
              <div class="stat-header">
                <span class="stat-name">未使用</span>
                <span class="stat-count">{{ stats.card_stats?.unused || 0 }}</span>
              </div>
              <div class="stat-bar">
                <div class="stat-bar-inner unused" :style="{ width: getCardPercent('unused') + '%' }"></div>
              </div>
            </div>
            <div class="stat-item">
              <div class="stat-header">
                <span class="stat-name">已激活</span>
                <span class="stat-count">{{ stats.card_stats?.activated || 0 }}</span>
              </div>
              <div class="stat-bar">
                <div class="stat-bar-inner activated" :style="{ width: getCardPercent('activated') + '%' }"></div>
              </div>
            </div>
            <div class="stat-item">
              <div class="stat-header">
                <span class="stat-name">已到期</span>
                <span class="stat-count">{{ stats.card_stats?.expired || 0 }}</span>
              </div>
              <div class="stat-bar">
                <div class="stat-bar-inner expired" :style="{ width: getCardPercent('expired') + '%' }"></div>
              </div>
            </div>
            <div class="stat-item">
              <div class="stat-header">
                <span class="stat-name">已封禁</span>
                <span class="stat-count">{{ stats.card_stats?.banned || 0 }}</span>
              </div>
              <div class="stat-bar">
                <div class="stat-bar-inner banned" :style="{ width: getCardPercent('banned') + '%' }"></div>
              </div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card header="API调用额度">
          <div class="api-quota">
            <div class="quota-header">
              <span>已用 / 总额度</span>
              <span class="quota-text">{{ stats.api?.used || 0 }} / {{ stats.api?.quota || 0 }}</span>
            </div>
            <el-progress
              :percentage="getApiPercent()"
              :color="getApiProgressColor()"
              :stroke-width="20"
            />
            <div class="quota-remaining">
              <el-icon><InfoFilled /></el-icon>
              剩余 {{ stats.api?.remaining || 0 }} 次调用
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px">
      <el-col :span="8">
        <el-card class="info-card">
          <div class="info-content">
            <div class="info-icon" style="background: #ecf5ff; color: #409eff">
              <el-icon :size="24"><User /></el-icon>
            </div>
            <div class="info-detail">
              <div class="info-value">{{ stats.agent_count || 0 }}</div>
              <div class="info-label">代理数量</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card class="info-card warning">
          <div class="info-content">
            <div class="info-icon" style="background: #fdf6ec; color: #e6a23c">
              <el-icon :size="24"><Clock /></el-icon>
            </div>
            <div class="info-detail">
              <div class="info-value">{{ stats.expiring_soon || 0 }}</div>
              <div class="info-label">即将到期卡密</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card class="info-card success">
          <div class="info-content">
            <div class="info-icon" style="background: #f0f9eb; color: #67c23a">
              <el-icon :size="24"><Connection /></el-icon>
            </div>
            <div class="info-detail">
              <div class="info-value">{{ stats.online_devices || 0 }}</div>
              <div class="info-label">在线设备</div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Monitor, Key, Wallet, Cpu, User, Clock, Connection, InfoFilled } from '@element-plus/icons-vue'
import { getMerchantDashboard } from '@/api/merchant/subAccount'

const loading = ref(false)
const stats = reactive({
  app_count: 0,
  card_stats: {},
  api: {},
  card_income: '0.00',
  agent_count: 0,
  online_devices: 0,
  expiring_soon: 0
})

async function fetchDashboard() {
  loading.value = true
  try {
    const res = await getMerchantDashboard()
    if (res.code === 0) {
      Object.assign(stats, res.data)
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function getCardTotal() {
  const s = stats.card_stats || {}
  return (s.unused || 0) + (s.activated || 0) + (s.expired || 0) + (s.banned || 0)
}

function getCardPercent(type) {
  const total = getCardTotal()
  if (total === 0) return 0
  const s = stats.card_stats || {}
  return Math.round((s[type] || 0) / total * 100)
}

function getApiPercent() {
  const quota = stats.api?.quota || 0
  const used = stats.api?.used || 0
  if (quota === 0) return 100
  return Math.min(Math.round(used / quota * 100), 100)
}

function getApiProgressColor() {
  const percent = getApiPercent()
  if (percent >= 90) return '#f56c6c'
  if (percent >= 70) return '#e6a23c'
  return '#67c23a'
}

onMounted(() => {
  fetchDashboard()
})
</script>

<style scoped>
.merchant-dashboard {
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

.card-stats {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 10px 0;
}

.stat-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stat-name {
  font-size: 13px;
  color: #606266;
}

.stat-count {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
}

.stat-bar {
  height: 8px;
  background: #f0f2f5;
  border-radius: 4px;
  overflow: hidden;
}

.stat-bar-inner {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s;
}

.stat-bar-inner.unused {
  background: #909399;
}

.stat-bar-inner.activated {
  background: #67c23a;
}

.stat-bar-inner.expired {
  background: #e6a23c;
}

.stat-bar-inner.banned {
  background: #f56c6c;
}

.api-quota {
  padding: 10px 0;
}

.quota-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  font-size: 14px;
  color: #606266;
}

.quota-text {
  font-weight: 600;
  color: #303133;
}

.quota-remaining {
  margin-top: 12px;
  font-size: 13px;
  color: #909399;
  display: flex;
  align-items: center;
  gap: 6px;
}

.info-card {
  border-radius: 8px;
}

.info-card.warning {
  border-left: 4px solid #e6a23c;
}

.info-card.success {
  border-left: 4px solid #67c23a;
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
