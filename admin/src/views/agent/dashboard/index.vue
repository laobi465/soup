<template>
  <div class="agent-dashboard">
    <div class="page-header">
      <h2>我的推广</h2>
    </div>

    <el-row :gutter="20">
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background: #409eff">
              <el-icon :size="28"><UserFilled /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.total_agents || 0 }}</div>
              <div class="stat-label">推广代理数</div>
            </div>
          </div>
          <div class="stat-footer increase">
            <el-icon><Top /></el-icon>
            今日新增 {{ stats.today_new || 0 }}
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background: #67c23a">
              <el-icon :size="28"><Wallet /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">¥{{ stats.total_commission || '0.00' }}</div>
              <div class="stat-label">累计佣金</div>
            </div>
          </div>
          <div class="stat-footer">
            <el-icon><Coin /></el-icon>
            可用 {{ stats.available_commission || '0.00' }}
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background: #e6a23c">
              <el-icon :size="28"><Money /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">¥{{ stats.frozen_commission || '0.00' }}</div>
              <div class="stat-label">冻结佣金</div>
            </div>
          </div>
          <div class="stat-footer warning">
            <el-icon><Timer /></el-icon>
            待结算
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-icon" style="background: #909399">
              <el-icon :size="28"><Connection /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ stats.subordinate_count || 0 }}</div>
              <div class="stat-label">下级人数</div>
            </div>
          </div>
          <div class="stat-footer">
            <el-icon><Share /></el-icon>
            一级下级
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px">
      <el-col :span="12">
        <el-card header="推广数据趋势">
          <div class="trend-chart">
            <div class="chart-placeholder">
              <el-icon :size="48"><TrendCharts /></el-icon>
              <p>数据趋势图表</p>
              <p class="chart-desc">（可集成 ECharts 实现折线图）</p>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card header="最近收益记录">
          <div class="commission-list">
            <div v-if="recentCommissions.length === 0" class="empty">
              暂无收益记录
            </div>
            <div v-for="(item, index) in recentCommissions" :key="index" class="commission-item">
              <div class="commission-left">
                <div class="commission-title">{{ item.title }}</div>
                <div class="commission-time">{{ item.time }}</div>
              </div>
              <div class="commission-amount positive">
                +¥{{ item.amount }}
              </div>
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
              <div class="info-value">{{ stats.total_agents || 0 }}</div>
              <div class="info-label">总推广人数</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card class="info-card success">
          <div class="info-content">
            <div class="info-icon" style="background: #f0f9eb; color: #67c23a">
              <el-icon :size="24"><Medal /></el-icon>
            </div>
            <div class="info-detail">
              <div class="info-value">{{ stats.level || 1 }}</div>
              <div class="info-label">代理等级</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card class="info-card warning">
          <div class="info-content">
            <div class="info-icon" style="background: #fdf6ec; color: #e6a23c">
              <el-icon :size="24"><Present /></el-icon>
            </div>
            <div class="info-detail">
              <div class="info-value">{{ stats.commission_rate || 0 }}%</div>
              <div class="info-label">佣金比例</div>
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
  UserFilled,
  Wallet,
  Money,
  Connection,
  User,
  Medal,
  Present,
  Top,
  Coin,
  Timer,
  Share,
  TrendCharts
} from '@element-plus/icons-vue'
import { getAgentDashboard } from '@/api/agent/index'

const loading = ref(false)
const stats = reactive({
  total_agents: 0,
  today_new: 0,
  total_commission: '0.00',
  available_commission: '0.00',
  frozen_commission: '0.00',
  subordinate_count: 0,
  level: 1,
  commission_rate: 0
})

const recentCommissions = ref([])

async function fetchDashboard() {
  loading.value = true
  try {
    const res = await getAgentDashboard()
    if (res.code === 0) {
      Object.assign(stats, res.data)
      recentCommissions.value = res.data.recent_commissions || []
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchDashboard()
})
</script>

<style scoped>
.agent-dashboard {
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
  padding-bottom: 12px;
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

.stat-footer {
  display: flex;
  align-items: center;
  gap: 4px;
  padding-top: 12px;
  border-top: 1px solid #f0f2f5;
  font-size: 12px;
  color: #606266;
}

.stat-footer.increase {
  color: #67c23a;
}

.stat-footer.warning {
  color: #e6a23c;
}

.trend-chart {
  padding: 20px 0;
}

.chart-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 250px;
  color: #c0c4cc;
  background: #fafafa;
  border-radius: 8px;
}

.chart-placeholder p {
  margin: 8px 0 0;
  font-size: 14px;
}

.chart-desc {
  font-size: 12px !important;
  color: #dcdfe6 !important;
}

.commission-list {
  max-height: 280px;
  overflow-y: auto;
}

.empty {
  text-align: center;
  padding: 40px 0;
  color: #909399;
  font-size: 14px;
}

.commission-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #f0f2f5;
}

.commission-item:last-child {
  border-bottom: none;
}

.commission-left {
  flex: 1;
}

.commission-title {
  font-size: 14px;
  color: #303133;
  margin-bottom: 4px;
}

.commission-time {
  font-size: 12px;
  color: #909399;
}

.commission-amount {
  font-size: 16px;
  font-weight: 600;
}

.commission-amount.positive {
  color: #67c23a;
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
