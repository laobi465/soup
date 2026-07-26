<template>
  <div class="app-detail" v-loading="loading">
    <div class="page-header">
      <el-button @click="goBack">
        <el-icon><ArrowLeft /></el-icon>
        返回
      </el-button>
      <h2>应用详情</h2>
      <div class="header-actions">
        <el-button @click="handleEdit">
          <el-icon><Edit /></el-icon>
          编辑
        </el-button>
        <el-button
          :type="app?.status === 1 ? 'warning' : 'success'"
          @click="handleToggleStatus"
        >
          {{ app?.status === 1 ? '停用应用' : '启用应用' }}
        </el-button>
        <el-button type="danger" @click="handleResetSecret">
          <el-icon><Refresh /></el-icon>
          重置Secret
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

    <el-dialog
      v-model="editDialogVisible"
      title="编辑应用"
      width="600px"
      @close="resetForm"
    >
      <el-form :model="form" ref="formRef" :rules="rules" label-width="100px">
        <el-form-item label="应用名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入应用名称" maxlength="100" show-word-limit />
        </el-form-item>
        <el-form-item label="应用图标">
          <el-input v-model="form.icon" placeholder="请输入图标URL" maxlength="255" />
        </el-form-item>
        <el-form-item label="版本号">
          <el-input v-model="form.version" placeholder="如: 1.0.0" maxlength="20" />
        </el-form-item>
        <el-form-item label="应用描述">
          <el-input
            v-model="form.description"
            type="textarea"
            :rows="3"
            placeholder="请输入应用描述"
            maxlength="500"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="绑定上限" prop="bind_limit">
          <el-input-number v-model="form.bind_limit" :min="1" :max="9999" />
          <span class="tip-text">单卡密最多可绑定的设备数</span>
        </el-form-item>
        <el-form-item label="IP白名单">
          <el-input
            v-model="form.ip_whitelist"
            type="textarea"
            :rows="4"
            placeholder="每行一个IP，支持单个IP或IP段，留空表示不限制"
          />
          <div class="tip-text">每行一个IP地址，留空则不限制访问IP</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleUpdate">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="resetDialogVisible"
      title="重置 AppSecret"
      width="500px"
      @close="resetPasswordForm"
    >
      <el-alert
        title="重置后旧的AppSecret将立即失效，请谨慎操作！"
        type="warning"
        :closable="false"
        show-icon
        style="margin-bottom: 20px"
      />
      <el-form :model="passwordForm" ref="passwordFormRef" :rules="passwordRules" label-width="100px">
        <el-form-item label="登录密码" prop="password">
          <el-input
            v-model="passwordForm.password"
            type="password"
            placeholder="请输入登录密码进行二次验证"
            show-password
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="resetDialogVisible = false">取消</el-button>
        <el-button type="danger" :loading="resetLoading" @click="confirmResetSecret">
          确认重置
        </el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="secretDialogVisible"
      title="新的 AppSecret"
      width="500px"
      @close="secretDialogVisible = false"
    >
      <el-alert
        title="请妥善保管您的新AppSecret，它只会显示一次！"
        type="warning"
        :closable="false"
        show-icon
        style="margin-bottom: 20px"
      />
      <div class="secret-content">
        <div class="secret-item">
          <span class="label">AppSecret：</span>
          <span class="value secret">{{ newSecret }}</span>
          <el-button type="primary" link @click="copyText(newSecret)">复制</el-button>
        </div>
      </div>
      <template #footer>
        <el-button type="primary" @click="secretDialogVisible = false">我已保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  ArrowLeft,
  Edit,
  Refresh,
  Monitor,
  CopyDocument
} from '@element-plus/icons-vue'
import {
  getApp,
  updateApp,
  updateAppStatus,
  resetAppSecret
} from '@/api/merchant/app'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const submitLoading = ref(false)
const resetLoading = ref(false)
const editDialogVisible = ref(false)
const resetDialogVisible = ref(false)
const secretDialogVisible = ref(false)
const formRef = ref(null)
const passwordFormRef = ref(null)
const app = ref(null)
const newSecret = ref('')

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

const form = reactive({
  name: '',
  icon: '',
  version: '',
  description: '',
  bind_limit: 1,
  ip_whitelist: ''
})

const passwordForm = reactive({
  password: ''
})

const rules = {
  name: [
    { required: true, message: '请输入应用名称', trigger: 'blur' },
    { max: 100, message: '应用名称不能超过100个字符', trigger: 'blur' }
  ],
  bind_limit: [
    { type: 'number', min: 1, message: '绑定上限不能小于1', trigger: 'blur' }
  ]
}

const passwordRules = {
  password: [
    { required: true, message: '请输入登录密码', trigger: 'blur' }
  ]
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

function resetForm() {
  form.name = ''
  form.icon = ''
  form.version = ''
  form.description = ''
  form.bind_limit = 1
  form.ip_whitelist = ''
  formRef.value?.clearValidate()
}

function resetPasswordForm() {
  passwordForm.password = ''
  passwordFormRef.value?.clearValidate()
}

function handleEdit() {
  if (!app.value) return
  form.name = app.value.name
  form.icon = app.value.icon || ''
  form.version = app.value.version || ''
  form.description = app.value.description || ''
  form.bind_limit = app.value.bind_limit
  form.ip_whitelist = Array.isArray(app.value.ip_whitelist)
    ? app.value.ip_whitelist.join('\n')
    : (app.value.ip_whitelist || '')
  editDialogVisible.value = true
}

function handleUpdate() {
  formRef.value.validate(async (valid) => {
    if (!valid) return

    submitLoading.value = true
    try {
      const res = await updateApp(appId, form)
      if (res.code === 0) {
        ElMessage.success('更新成功')
        editDialogVisible.value = false
        fetchDetail()
      }
    } finally {
      submitLoading.value = false
    }
  })
}

function handleToggleStatus() {
  if (!app.value) return
  const action = app.value.status === 1 ? '停用' : '启用'
  ElMessageBox.confirm(
    `确定要${action}应用「${app.value.name}」吗？${app.value.status === 1 ? '停用后所有该应用的卡密验证将失效。' : ''}`,
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

function handleResetSecret() {
  resetPasswordForm()
  resetDialogVisible.value = true
}

function confirmResetSecret() {
  passwordFormRef.value.validate(async (valid) => {
    if (!valid) return

    resetLoading.value = true
    try {
      const res = await resetAppSecret(appId, passwordForm.password)
      if (res.code === 0) {
        resetDialogVisible.value = false
        newSecret.value = res.data.app_secret
        secretDialogVisible.value = true
        ElMessage.success('重置成功')
      }
    } finally {
      resetLoading.value = false
    }
  })
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

.tip-text {
  margin-left: 10px;
  font-size: 12px;
  color: #909399;
}

.secret-content {
  padding: 20px;
  background: #f5f7fa;
  border-radius: 8px;
}

.secret-item {
  display: flex;
  align-items: center;
  word-break: break-all;
}

.secret-item .label {
  width: 80px;
  color: #606266;
  flex-shrink: 0;
}

.secret-item .value {
  flex: 1;
  font-family: monospace;
}

.secret-item .value.secret {
  color: #e6a23c;
  font-weight: 600;
}
</style>
