<template>
  <div class="agent-detail">
    <div class="page-header">
      <el-button @click="goBack">
        <el-icon><ArrowLeft /></el-icon>
        返回
      </el-button>
      <h2>代理详情</h2>
    </div>

    <div v-loading="loading" class="detail-content">
      <el-row :gutter="20">
        <el-col :span="8">
          <el-card>
            <template #header>
              <span>基本信息</span>
            </template>
            <div class="info-item" v-if="agentInfo">
              <div class="item">
                <span class="label">用户名</span>
                <span class="value">{{ agentInfo.username }}</span>
              </div>
              <div class="item">
                <span class="label">邮箱</span>
                <span class="value">{{ agentInfo.email }}</span>
              </div>
              <div class="item">
                <span class="label">等级</span>
                <el-tag :type="getLevelTagType(agentInfo.level)">{{ agentInfo.level_text }}</el-tag>
              </div>
              <div class="item">
                <span class="label">上级</span>
                <span class="value">{{ agentInfo.parent_username || '-' }}</span>
              </div>
              <div class="item">
                <span class="label">邀请码</span>
                <span class="value">{{ agentInfo.invite_code }}</span>
              </div>
              <div class="item">
                <span class="label">状态</span>
                <el-tag :type="agentInfo.status === 1 ? 'success' : 'danger'">
                  {{ agentInfo.status_text }}
                </el-tag>
              </div>
              <div class="item">
                <span class="label">加入时间</span>
                <span class="value">{{ agentInfo.created_at }}</span>
              </div>
            </div>
          </el-card>
        </el-col>

        <el-col :span="16">
          <el-card>
            <template #header>
              <span>收益信息</span>
            </template>
            <div class="earnings-grid" v-if="agentInfo">
              <div class="earnings-card">
                <div class="earnings-label">累计收益</div>
                <div class="earnings-value">¥{{ agentInfo.total_earnings }}</div>
              </div>
              <div class="earnings-card">
                <div class="earnings-label">可用余额</div>
                <div class="earnings-value available">¥{{ agentInfo.available_balance }}</div>
              </div>
              <div class="earnings-card">
                <div class="earnings-label">冻结余额</div>
                <div class="earnings-value frozen">¥{{ agentInfo.frozen_balance }}</div>
              </div>
              <div class="earnings-card">
                <div class="earnings-label">佣金比例</div>
                <div class="earnings-value">
                  {{ (agentInfo.commission_rate * 100).toFixed(0) }}%
                </div>
              </div>
            </div>
          </el-card>

          <el-card style="margin-top: 20px;">
            <template #header>
              <span>下级团队（{{ agentInfo?.child_count || 0 }}人）</span>
            </template>
            <div class="team-stats" v-if="teamStats">
              <div class="stat-item">
                <div class="stat-label">一级</div>
                <div class="stat-value">{{ teamStats.level1 }}</div>
              </div>
              <div class="stat-item">
                <div class="stat-label">二级</div>
                <div class="stat-value">{{ teamStats.level2 }}</div>
              </div>
              <div class="stat-item">
                <div class="stat-label">三级</div>
                <div class="stat-value">{{ teamStats.level3 }}</div>
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
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft } from '@element-plus/icons-vue'
import { getAgentDetail } from '@/api/merchant/agent'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const agentInfo = ref(null)
const teamStats = reactive({
  level1: 0,
  level2: 0,
  level3: 0
})

function getLevelTagType(level) {
  const map = {
    1: 'success',
    2: 'warning',
    3: 'info'
  }
  return map[level] || 'info'
}

async function fetchDetail() {
  const id = route.params.id
  if (!id) return

  loading.value = true
  try {
    const res = await getAgentDetail(id)
    if (res.code === 0) {
      agentInfo.value = res.data
    }
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.push({ path: '/merchant/agents' })
}

onMounted(() => {
  fetchDetail()
})
</script>

<style scoped>
.agent-detail {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
}

.info-item .item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #f5f7fa;
}

.info-item .item:last-child {
  border-bottom: none;
}

.info-item .label {
  color: #909399;
  font-size: 14px;
}

.info-item .value {
  color: #303133;
  font-size: 14px;
}

.earnings-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.earnings-card {
  text-align: center;
  padding: 20px;
  background: #f5f7fa;
  border-radius: 8px;
}

.earnings-label {
  font-size: 13px;
  color: #909399;
  margin-bottom: 8px;
}

.earnings-value {
  font-size: 24px;
  font-weight: 600;
  color: #303133;
}

.earnings-value.available {
  color: #67c23a;
}

.earnings-value.frozen {
  color: #e6a23c;
}

.team-stats {
  display: flex;
  justify-content: space-around;
  padding: 20px 0;
}

.stat-item {
  text-align: center;
}

.stat-label {
  font-size: 13px;
  color: #909399;
  margin-bottom: 8px;
}

.stat-value {
  font-size: 28px;
  font-weight: 600;
  color: #409eff;
}
</style>
