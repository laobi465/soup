<template>
  <div class="system-config">
    <div class="page-header">
      <h2>系统配置</h2>
      <el-button type="primary" plain @click="handleClearCache">清除缓存</el-button>
    </div>

    <div v-loading="loading" class="config-content">
      <el-tabs v-model="activeTab" @tab-change="handleTabChange">
        <el-tab-pane label="基础配置" name="basic">
          <el-form :model="forms.basic" :rules="rules.basic" ref="basicFormRef" label-width="140px">
            <el-form-item label="站点名称" prop="site_name">
              <el-input v-model="forms.basic.site_name" placeholder="请输入站点名称" />
            </el-form-item>
            <el-form-item label="站点Logo" prop="site_logo">
              <el-input v-model="forms.basic.site_logo" placeholder="请输入Logo地址" />
            </el-form-item>
            <el-form-item label="ICP备案号" prop="site_icp">
              <el-input v-model="forms.basic.site_icp" placeholder="请输入ICP备案号" />
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <el-tab-pane label="API配置" name="api">
          <el-form :model="forms.api" :rules="rules.api" ref="apiFormRef" label-width="180px">
            <el-form-item label="API每分钟限流" prop="api_rate_limit_per_minute">
              <el-input-number v-model="forms.api.api_rate_limit_per_minute" :min="1" :max="10000" />
              <span style="margin-left: 10px; color: #999;">次/分钟</span>
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <el-tab-pane label="邮件配置" name="email">
          <el-form :model="forms.email" :rules="rules.email" ref="emailFormRef" label-width="160px">
            <el-form-item label="SMTP服务器" prop="email_smtp_host">
              <el-input v-model="forms.email.email_smtp_host" placeholder="请输入SMTP服务器地址" />
            </el-form-item>
            <el-form-item label="SMTP端口" prop="email_smtp_port">
              <el-input-number v-model="forms.email.email_smtp_port" :min="1" :max="65535" />
            </el-form-item>
            <el-form-item label="发件人邮箱" prop="email_from">
              <el-input v-model="forms.email.email_from" placeholder="请输入发件人邮箱" />
            </el-form-item>
            <el-form-item label="发件人名称" prop="email_from_name">
              <el-input v-model="forms.email.email_from_name" placeholder="请输入发件人名称" />
            </el-form-item>
            <el-form-item label="邮箱账号" prop="email_username">
              <el-input v-model="forms.email.email_username" placeholder="请输入邮箱账号" />
            </el-form-item>
            <el-form-item label="邮箱密码" prop="email_password">
              <el-input v-model="forms.email.email_password" type="password" placeholder="请输入邮箱密码" show-password />
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <el-tab-pane label="卡密配置" name="card">
          <el-form :model="forms.card" :rules="rules.card" ref="cardFormRef" label-width="180px">
            <el-form-item label="默认卡密前缀" prop="card_prefix_default">
              <el-input v-model="forms.card.card_prefix_default" placeholder="请输入默认前缀" />
            </el-form-item>
            <el-form-item label="默认卡密长度" prop="card_length_default">
              <el-input-number v-model="forms.card.card_length_default" :min="8" :max="64" />
            </el-form-item>
            <el-form-item label="默认绑定设备数" prop="card_bind_limit_default">
              <el-input-number v-model="forms.card.card_bind_limit_default" :min="1" :max="100" />
            </el-form-item>
            <el-form-item label="心跳间隔（秒）" prop="card_heartbeat_interval">
              <el-input-number v-model="forms.card.card_heartbeat_interval" :min="10" :max="3600" />
            </el-form-item>
            <el-form-item label="离线超时（秒）" prop="card_offline_timeout">
              <el-input-number v-model="forms.card.card_offline_timeout" :min="30" :max="86400" />
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <el-tab-pane label="安全配置" name="user">
          <el-form :model="forms.user" :rules="rules.user" ref="userFormRef" label-width="180px">
            <el-form-item label="开放注册" prop="register_enable">
              <el-switch v-model="forms.user.register_enable" :active-value="1" :inactive-value="0" />
            </el-form-item>
            <el-form-item label="注册默认角色" prop="register_default_role">
              <el-select v-model="forms.user.register_default_role">
                <el-option label="管理员" :value="1" />
                <el-option label="商户" :value="2" />
                <el-option label="代理商" :value="3" />
              </el-select>
            </el-form-item>
            <el-form-item label="登录最大失败次数" prop="login_max_fail">
              <el-input-number v-model="forms.user.login_max_fail" :min="1" :max="100" />
            </el-form-item>
            <el-form-item label="登录锁定时间（秒）" prop="login_lock_time">
              <el-input-number v-model="forms.user.login_lock_time" :min="60" :max="86400" />
            </el-form-item>
          </el-form>
        </el-tab-pane>
      </el-tabs>

      <div class="form-actions">
        <el-button type="primary" @click="handleSave" :loading="submitting">保存配置</el-button>
        <el-button @click="handleReset">重置</el-button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getSystemConfig, saveSystemConfig, clearConfigCache } from '@/api/admin/config'

const loading = ref(false)
const submitting = ref(false)
const activeTab = ref('basic')

const forms = reactive({
  basic: {},
  api: {},
  email: {},
  card: {},
  user: {}
})

const rules = {
  basic: {
    site_name: [{ required: true, message: '请输入站点名称', trigger: 'blur' }]
  },
  api: {},
  email: {},
  card: {},
  user: {}
}

function handleTabChange(tab) {
  activeTab.value = tab
}

async function fetchConfigs() {
  loading.value = true
  try {
    const res = await getSystemConfig()
    if (res.code === 0 && res.data && res.data.groups) {
      const groups = res.data.groups
      for (const groupName in groups) {
        if (forms[groupName]) {
          const configs = groups[groupName]
          for (const cfg of configs) {
            forms[groupName][cfg.key] = cfg.value
          }
        }
      }
    }
  } finally {
    loading.value = false
  }
}

async function handleSave() {
  submitting.value = true
  try {
    const allConfig = {}
    for (const group in forms) {
      Object.assign(allConfig, forms[group])
    }
    const res = await saveSystemConfig(allConfig)
    if (res.code === 0) {
      ElMessage.success('配置保存成功')
    }
  } finally {
    submitting.value = false
  }
}

async function handleReset() {
  await fetchConfigs()
  ElMessage.info('配置已重置')
}

async function handleClearCache() {
  try {
    const res = await clearConfigCache()
    if (res.code === 0) {
      ElMessage.success('缓存清除成功')
    }
  } catch (e) {
    console.error(e)
  }
}

onMounted(() => {
  fetchConfigs()
})
</script>

<style scoped>
.system-config {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
}

.config-content {
  background: #fff;
  padding: 20px;
  border-radius: 8px;
}

.form-actions {
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid #ebeef5;
  display: flex;
  justify-content: center;
  gap: 12px;
}
</style>
