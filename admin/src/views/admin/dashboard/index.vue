<template>
  <div class="admin-dashboard">
    <div class="page-header">
      <h2>数据概览</h2>
    </div>

    <el-row :gutter="20">
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background: #409eff">
              <el-icon :size="28"><UserFilled /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.merchant?.total || 0 }}</div>
              <div class="stat-label">商户总数</div>
              <div class="stat-trend up">
                <el-icon><Top /></el-icon>
                今日 +{{ stats.merchant?.today_new || 0 }}
              </div>
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
              <div class="stat-value">{{ stats.card?.total || 0 }}</div>
              <div class="stat-label">卡密总数</div>
              <div class="stat-trend up">
                <el-icon><Top /></el-icon>
                今日生成 +{{ stats.card?.today_generated || 0 }}
              </div>
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
              <div class="stat-value">¥{{ stats.order?.amount || '0.00' }}</div>
              <div class="stat-label">交易总额</div>
              <div class="stat-trend">
                订单数 {{ stats.order?.total || 0 }}
              </div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background: #f56c6c">
              <el-icon :size="28"><DataAnalysis /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.api?.today || 0 }}</div>
              <div class="stat-label">今日API调用</div>
              <div class="stat-trend">
                本月 {{ stats.api?.month || 0 }}
              </div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px">
      <el-col :span="12">
        <el-card class="chart-card">
          <template #header>
            <div class="card-header">
              <span>数据趋势</span>
              <el-radio-group v-model="trendRange" size="small" @change="fetchTrend">
                <el-radio-button value="week">近7天</el-radio-button>
                <el-radio-button value="month">近30天</el-radio-button>
              </el-radio-group>
            </div>
          </template>
          <div class="chart-container">
            <div v-for="(item, index) in trendData.dates" :key="index" class="trend-item">
              <div class="trend-date">{{ item }}</div>
              <div class="trend-bars">
                <div class="trend-bar merchant" :style="{ height: getBarHeight(trendData.merchants?.[index] || 0, 'merchant') + '%' }"></div>
                <div class="trend-bar api" :style="{ height: getBarHeight(trendData.api_calls?.[index] || 0, 'api') + '%' }"></div>
              </div>
            </div>
          </div>
          <div class="chart-legend">
            <span class="legend-item"><span class="legend-dot merchant"></span>新增商户</span>
            <span class="legend-item"><span class="legend-dot api"></span>API调用</span>
            <span class="legend-item"><span class="legend-dot order"></span>交易额(¥)</span>
          </div>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card class="chart-card">
          <template #header>
            <span>实时数据</span>
          </template>
          <el-descriptions :column="2" border size="default">
            <el-descriptions-item label="在线设备">
              <span class="highlight">{{ stats.online_devices || 0 }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="待处理工单">
              <span class="highlight warning">{{ stats.pending_tickets || 0 }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="代理总数">
              <span>{{ stats.agent?.total || 0 }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="平台收入">
              <span class="highlight success">¥{{ stats.order?.platform_income || '0.00' }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="本周API调用">
              <span>{{ stats.api?.week || 0 }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="本月API调用">
              <span>{{ stats.api?.month || 0 }}</span>
            </el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { UserFilled, Key, Wallet, DataAnalysis, Top } from '@element-plus/icons-vue'
import { getDashboard, getStatsTrend } from '@/api/admin/dashboard'

const loading = ref(false)
const stats = reactive({
  merchant: {},
  card: {},
  order: {},
  api: {}
})

const trendRange = ref('week')
const trendData = reactive({
  dates: [],
  merchants: [],
  orders: [],
  api_calls: []
})

async function fetchDashboard() {
  loading.value = true
  try {
    const res = await getDashboard()
    if (res.code === 0) {
      Object.assign(stats, res.data)
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function fetchTrend() {
  try {
    const res = await getStatsTrend(trendRange.value)
    if (res.code === 0) {
      Object.assign(trendData, res.data)
    }
  } catch (e) {
    console.error(e)
  }
}

function getBarHeight(value, type) {
  const maxValues = {
    merchant: 100,
    api: 10000,
    order: 10000
  }
  const max = maxValues[type] || 100
  const percent = Math.min((value / max) * 100, 100)
  return Math.max(percent, 2)
}

onMounted(() => {
  fetchDashboard()
  fetchTrend()
})
</script>

<style scoped>
.admin-dashboard {
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
  min-width: 0;
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
  margin-bottom: 4px;
}

.stat-trend {
  font-size: 12px;
  color: #909399;
  display: flex;
  align-items: center;
  gap: 4px;
}

.stat-trend.up {
  color: #67c23a;
}

.chart-card {
  border-radius: 8px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.chart-container {
  display: flex;
  align-items: flex-end;
  justify-content: space-around;
  height: 200px;
  padding: 10px 0;
  border-bottom: 1px solid #ebeef5;
}

.trend-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.trend-date {
  font-size: 12px;
  color: #909399;
  order: 2;
}

.trend-bars {
  display: flex;
  align-items: flex-end;
  gap: 4px;
  height: 160px;
  order: 1;
}

.trend-bar {
  width: 12px;
  border-radius: 4px 4px 0 0;
  transition: height 0.3s;
}

.trend-bar.merchant {
  background: linear-gradient(180deg, #409eff 0%, #66b1ff 100%);
}

.trend-bar.api {
  background: linear-gradient(180deg, #67c23a 0%, #85ce61 100%);
}

.chart-legend {
  display: flex;
  justify-content: center;
  gap: 20px;
  margin-top: 16px;
  font-size: 12px;
  color: #606266;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}

.legend-dot.merchant {
  background: #409eff;
}

.legend-dot.api {
  background: #67c23a;
}

.legend-dot.order {
  background: #e6a23c;
}

.highlight {
  font-size: 18px;
  font-weight: 600;
  color: #409eff;
}

.highlight.warning {
  color: #e6a23c;
}

.highlight.success {
  color: #67c23a;
}
</style>
