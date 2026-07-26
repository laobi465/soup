<template>
  <div class="merchant-package">
    <div class="page-header">
      <h2>套餐管理</h2>
    </div>

    <div v-loading="loading" class="package-content">
      <el-card class="current-package-card">
        <template #header>
          <div class="card-header">
            <span>当前套餐</span>
          </div>
        </template>
        <div class="current-package" v-if="currentInfo.package">
          <div class="package-header">
            <div class="package-name">{{ currentInfo.package.name }}</div>
            <el-tag v-if="currentInfo.merchant?.is_package_expired" type="danger">已过期</el-tag>
            <el-tag v-else type="success">使用中</el-tag>
          </div>
          <div class="package-expire">
            到期时间：{{ currentInfo.merchant?.package_expire || '永久有效' }}
          </div>
          <div class="package-features">
            <div class="feature-item">
              <el-icon><Check /></el-icon>
              <span>应用数量：{{ currentInfo.package.app_limit === 0 ? '不限' : currentInfo.package.app_limit }}</span>
            </div>
            <div class="feature-item">
              <el-icon><Check /></el-icon>
              <span>卡密数量：{{ currentInfo.package.card_limit === 0 ? '不限' : currentInfo.package.card_limit }}</span>
            </div>
            <div class="feature-item">
              <el-icon><Check /></el-icon>
              <span>日API调用：{{ currentInfo.package.api_limit_day === 0 ? '不限' : currentInfo.package.api_limit_day }}</span>
            </div>
            <div class="feature-item">
              <el-icon><Check /></el-icon>
              <span>在线设备：{{ currentInfo.package.online_limit === 0 ? '不限' : currentInfo.package.online_limit }}</span>
            </div>
            <div class="feature-item">
              <el-icon><Check /></el-icon>
              <span>子账号数：{{ currentInfo.package.sub_account_limit === 0 ? '不限' : currentInfo.package.sub_account_limit }}</span>
            </div>
          </div>
          <div class="quota-section">
            <h4>配额使用情况</h4>
            <div class="quota-item">
              <div class="quota-label">应用配额</div>
              <div class="quota-bar">
                <el-progress
                  :percentage="appUsagePercent"
                  :status="appUsagePercent >= 90 ? 'exception' : ''"
                />
              </div>
              <div class="quota-value">
                {{ currentInfo.merchant?.remaining_apps >= 0
                  ? currentInfo.merchant?.remaining_apps + ' 剩余'
                  : '不限量'
                }}
              </div>
            </div>
            <div class="quota-item">
              <div class="quota-label">卡密配额</div>
              <div class="quota-bar">
                <el-progress
                  :percentage="cardUsagePercent"
                  :status="cardUsagePercent >= 90 ? 'exception' : ''"
                />
              </div>
              <div class="quota-value">
                {{ currentInfo.merchant?.remaining_cards >= 0
                  ? currentInfo.merchant?.remaining_cards + ' 剩余'
                  : '不限量'
                }}
              </div>
            </div>
          </div>
        </div>
        <div v-else class="no-package">
          <el-empty description="暂未开通套餐" />
        </div>
      </el-card>

      <el-card class="packages-card">
        <template #header>
          <div class="card-header">
            <span>可购买套餐</span>
          </div>
        </template>
        <div class="packages-list">
          <el-row :gutter="20">
            <el-col :span="8" v-for="pkg in packages" :key="pkg.id">
              <div class="package-card" :class="{ recommended: pkg.recommended }">
                <div v-if="pkg.recommended" class="recommended-badge">推荐</div>
                <div class="package-card-header">
                  <h3>{{ pkg.name }}</h3>
                  <div class="price">
                    <span class="symbol">¥</span>
                    <span class="amount">{{ pkg.price_month }}</span>
                    <span class="unit">/月</span>
                  </div>
                </div>
                <div class="package-card-features">
                  <div class="feature-item">
                    <el-icon><Check /></el-icon>
                    <span>应用数量：{{ pkg.app_limit === 0 ? '不限' : pkg.app_limit }}</span>
                  </div>
                  <div class="feature-item">
                    <el-icon><Check /></el-icon>
                    <span>卡密数量：{{ pkg.card_limit === 0 ? '不限' : pkg.card_limit }}</span>
                  </div>
                  <div class="feature-item">
                    <el-icon><Check /></el-icon>
                    <span>日API调用：{{ pkg.api_limit_day === 0 ? '不限' : pkg.api_limit_day }}</span>
                  </div>
                  <div class="feature-item">
                    <el-icon><Check /></el-icon>
                    <span>在线设备：{{ pkg.online_limit === 0 ? '不限' : pkg.online_limit }}</span>
                  </div>
                  <div class="feature-item">
                    <el-icon><Check /></el-icon>
                    <span>子账号数：{{ pkg.sub_account_limit === 0 ? '不限' : pkg.sub_account_limit }}</span>
                  </div>
                </div>
                <div class="package-card-footer">
                  <el-select v-model="durationMap[pkg.id]" placeholder="选择时长" size="small" style="width: 100%; margin-bottom: 12px">
                    <el-option label="月付" value="month" />
                    <el-option label="季付" value="quarter" />
                    <el-option label="年付" value="year" />
                  </el-select>
                  <el-button
                    type="primary"
                    style="width: 100%"
                    @click="handleUpgrade(pkg)"
                  >
                    {{ currentInfo.package?.id === pkg.id ? '续费' : '立即开通' }}
                  </el-button>
                </div>
              </div>
            </el-col>
          </el-row>
        </div>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Check } from '@element-plus/icons-vue'
import { getCurrentPackage, getPackageList, upgradePackage } from '@/api/merchant/package'

const loading = ref(false)
const packages = ref([])
const durationMap = reactive({})

const currentInfo = reactive({
  merchant: null,
  package: null
})

const appUsagePercent = computed(() => {
  if (!currentInfo.merchant || !currentInfo.package) return 0
  if (currentInfo.merchant.remaining_apps < 0) return 0
  const total = currentInfo.merchant.app_quota
  const used = total - currentInfo.merchant.remaining_apps
  return total > 0 ? Math.min(100, Math.round((used / total) * 100)) : 0
})

const cardUsagePercent = computed(() => {
  if (!currentInfo.merchant || !currentInfo.package) return 0
  if (currentInfo.merchant.remaining_cards < 0) return 0
  const total = currentInfo.merchant.card_quota
  const used = currentInfo.merchant.card_used
  return total > 0 ? Math.min(100, Math.round((used / total) * 100)) : 0
})

async function fetchCurrentPackage() {
  try {
    const res = await getCurrentPackage()
    if (res.code === 0) {
      currentInfo.merchant = res.data.merchant
      currentInfo.package = res.data.package
    }
  } catch (e) {
    console.error(e)
  }
}

async function fetchPackages() {
  try {
    const res = await getPackageList()
    if (res.code === 0) {
      packages.value = res.data.map((pkg, index) => ({
        ...pkg,
        recommended: index === 1
      }))
      packages.value.forEach(pkg => {
        durationMap[pkg.id] = 'month'
      })
    }
  } catch (e) {
    console.error(e)
  }
}

async function handleUpgrade(pkg) {
  const duration = durationMap[pkg.id] || 'month'
  const priceField = 'price_' + duration
  const amount = pkg[priceField]
  const durationText = { month: '月付', quarter: '季付', year: '年付' }[duration]

  ElMessageBox.confirm(
    `确定要${currentInfo.package?.id === pkg.id ? '续费' : '升级/开通'}「${pkg.name}」${durationText}套餐吗？\n金额：¥${amount}`,
    '提示',
    {
      type: 'warning',
      confirmButtonText: '确定',
      cancelButtonText: '取消'
    }
  ).then(async () => {
    loading.value = true
    try {
      const res = await upgradePackage({
        package_id: pkg.id,
        duration: duration
      })
      if (res.code === 0) {
        ElMessage.success('订单创建成功，请完成支付')
        fetchCurrentPackage()
      }
    } finally {
      loading.value = false
    }
  }).catch(() => {})
}

onMounted(async () => {
  loading.value = true
  try {
    await Promise.all([fetchCurrentPackage(), fetchPackages()])
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.merchant-package {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
}

.package-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.card-header {
  font-weight: 600;
}

.current-package-card {
  margin-bottom: 20px;
}

.current-package .package-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
}

.package-name {
  font-size: 24px;
  font-weight: 600;
  color: #303133;
}

.package-expire {
  color: #909399;
  margin-bottom: 20px;
}

.package-features {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-bottom: 24px;
}

.feature-item {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #606266;
  font-size: 14px;
}

.feature-item .el-icon {
  color: #67c23a;
}

.quota-section {
  padding-top: 20px;
  border-top: 1px solid #ebeef5;
}

.quota-section h4 {
  margin: 0 0 16px 0;
  font-size: 15px;
}

.quota-item {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
}

.quota-label {
  width: 80px;
  color: #606266;
  font-size: 14px;
}

.quota-bar {
  flex: 1;
}

.quota-value {
  width: 100px;
  text-align: right;
  color: #909399;
  font-size: 13px;
}

.no-package {
  padding: 40px 0;
}

.packages-card {
  margin-top: 20px;
}

.packages-list {
  padding: 20px 0;
}

.package-card {
  position: relative;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  padding: 24px;
  background: #fff;
  transition: all 0.3s;
  text-align: center;
}

.package-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
}

.package-card.recommended {
  border-color: #409eff;
}

.recommended-badge {
  position: absolute;
  top: 0;
  right: 20px;
  background: #409eff;
  color: #fff;
  padding: 4px 12px;
  font-size: 12px;
  border-radius: 0 0 4px 4px;
}

.package-card-header h3 {
  margin: 0 0 12px 0;
  font-size: 18px;
  color: #303133;
}

.price {
  margin-bottom: 20px;
  color: #e6a23c;
}

.price .symbol {
  font-size: 14px;
}

.price .amount {
  font-size: 32px;
  font-weight: 600;
}

.price .unit {
  font-size: 14px;
  color: #909399;
}

.package-card-features {
  text-align: left;
  margin-bottom: 20px;
}

.package-card-features .feature-item {
  margin-bottom: 10px;
}

.package-card-footer {
  padding-top: 16px;
  border-top: 1px solid #ebeef5;
}
</style>
