<template>
  <div class="agent-dashboard">
    <div class="page-header">
      <h2>代理中心</h2>
    </div>

    <div v-loading="loading" class="dashboard-content">
      <el-row :gutter="20">
        <el-col :span="8">
          <el-card class="stat-card income">
            <div class="stat-icon">
              <el-icon :size="32"><Wallet /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-label">累计收益</div>
              <div class="stat-value">¥{{ agentInfo?.total_earnings || '0.00' }}</div>
            </div>
          </el-card>
        </el-col>
        <el-col :span="8">
          <el-card class="stat-card available">
            <div class="stat-icon">
              <el-icon :size="32"><Money /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-label">可用余额</div>
              <div class="stat-value">¥{{ agentInfo?.available_balance || '0.00' }}</div>
            </div>
          </el-card>
        </el-col>
        <el-col :span="8">
          <el-card class="stat-card frozen">
            <div class="stat-icon">
              <el-icon :size="32"><Lock /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-label">冻结余额</div>
              <div class="stat-value">¥{{ agentInfo?.frozen_balance || '0.00' }}</div>
            </div>
          </el-card>
        </el-col>
      </el-row>

      <el-row :gutter="20" style="margin-top: 20px;">
        <el-col :span="12">
          <el-card>
            <template #header>
              <span>今日数据</span>
            </template>
            <div class="today-stats">
              <div class="today-item">
                <div class="today-label">今日订单</div>
                <div class="today-value">{{ dashboard?.today_orders || 0 }}</div>
              </div>
              <div class="today-item">
                <div class="today-label">今日佣金</div>
                <div class="today-value">¥{{ dashboard?.today_commission || '0.00' }}</div>
              </div>
              <div class="today-item">
                <div class="today-label">累计订单</div>
                <div class="today-value">{{ dashboard?.total_orders || 0 }}</div>
              </div>
              <div class="today-item">
                <div class="today-label">团队人数</div>
                <div class="today-value">{{ dashboard?.team_count || 0 }}</div>
              </div>
            </div>
          </el-card>
        </el-col>
        <el-col :span="12">
          <el-card>
            <template #header>
              <span>推广信息</span>
            </template>
            <div class="invite-info" v-if="agentInfo">
              <div class="invite-item">
                <span class="label">代理等级</span>
                <el-tag :type="getLevelTagType(agentInfo.level)">
                  {{ agentInfo.level_text }}
                </el-tag>
              </div>
              <div class="invite-item">
                <span class="label">佣金比例</span>
                <span class="value">{{ (agentInfo.commission_rate * 100).toFixed(0) }}%</span>
              </div>
              <div class="invite-item">
                <span class="label">邀请码</span>
                <span class="value invite-code">{{ agentInfo.invite_code }}</span>
              </div>
              <div class="invite-actions">
                <el-button type="primary" @click="goInvite">查看推广链接</el-button>
              </div>
            </div>
          </el-card>
        </el-col>
      </el-row>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Wallet, Money, Lock } from '@element-plus/icons-vue'
import { getAgentDashboard } from '@/api/agent'

const router = useRouter()
const loading = ref(false)
const agentInfo = ref(null)
const dashboard = ref(null)

function getLevelTagType(level) {
  const map = {
    1: 'success',
    2: 'warning',
    3: 'info'
  }
  return map[level] || 'info'
}

async function fetchDashboard() {
  loading.value = true
  try {
    const res = await getAgentDashboard()
    if (res.code === 0) {
      agentInfo.value = res.data.agent_info
      dashboard.value = res.data
    }
  } finally {
    loading.value = false
  }
}

function goInvite() {
  router.push({ path: '/agent/invite' })
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
  display: flex;
  align-items: center;
  gap: 16px;
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}

.stat-card.income .stat-icon {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-card.available .stat-icon {
  background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.stat-card.frozen .stat-icon {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-label {
  font-size: 14px;
  color: #909399;
  margin-bottom: 6px;
}

.stat-value {
  font-size: 28px;
  font-weight: 600;
  color: #303133;
}

.today-stats {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
  padding: 10px 0;
}

.today-item {
  text-align: center;
}

.today-label {
  font-size: 13px;
  color: #909399;
  margin-bottom: 8px;
}

.today-value {
  font-size: 24px;
  font-weight: 600;
  color: #409eff;
}

.invite-info {
  padding: 10px 0;
}

.invite-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #f5f7fa;
}

.invite-item:last-of-type {
  border-bottom: none;
}

.invite-item .label {
  color: #909399;
  font-size: 14px;
}

.invite-item .value {
  color: #303133;
  font-size: 14px;
  font-weight: 500;
}

.invite-code {
  font-family: monospace;
  letter-spacing: 1px;
}

.invite-actions {
  margin-top: 20px;
  text-align: center;
}
</style>
