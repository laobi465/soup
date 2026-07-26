<template>
  <div class="payment-config">
    <div class="page-header">
      <h2>支付配置</h2>
    </div>

    <div v-loading="loading" class="config-content">
      <el-card class="config-card">
        <template #header>
          <div class="card-header">
            <span>彩虹易支付配置</span>
            <el-switch
              v-model="form.caihong_enabled"
              :active-value="1"
              :inactive-value="0"
              @change="handleStatusChange"
            />
          </div>
        </template>

        <el-form :model="form" :rules="rules" ref="formRef" label-width="120px">
          <el-form-item label="API地址" prop="caihong_api_url">
            <el-input v-model="form.caihong_api_url" placeholder="请输入易支付API地址" />
          </el-form-item>

          <el-form-item label="商户ID(PID)" prop="caihong_pid">
            <el-input v-model="form.caihong_pid" placeholder="请输入商户ID" />
          </el-form-item>

          <el-form-item label="商户密钥" prop="caihong_key">
            <el-input v-model="form.caihong_key" type="password" placeholder="请输入商户密钥" show-password />
          </el-form-item>
        </el-form>

        <template #footer>
          <el-button type="primary" @click="handleSubmit" :loading="submitting">保存配置</el-button>
        </template>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getPaymentConfig, updatePaymentConfig } from '@/api/admin/payment'

const loading = ref(false)
const submitting = ref(false)
const formRef = ref(null)

const form = reactive({
  caihong_enabled: 0,
  caihong_api_url: '',
  caihong_pid: '',
  caihong_key: ''
})

const rules = {
  caihong_api_url: [{ required: true, message: '请输入API地址', trigger: 'blur' }],
  caihong_pid: [{ required: true, message: '请输入商户ID', trigger: 'blur' }],
  caihong_key: [{ required: true, message: '请输入商户密钥', trigger: 'blur' }]
}

async function fetchConfig() {
  loading.value = true
  try {
    const res = await getPaymentConfig()
    if (res.code === 0 && res.data && res.data.caihong) {
      const cfg = res.data.caihong
      form.caihong_enabled = cfg.enabled || 0
      form.caihong_api_url = cfg.api_url || ''
      form.caihong_pid = cfg.pid || ''
      form.caihong_key = cfg.key || ''
    }
  } finally {
    loading.value = false
  }
}

function handleStatusChange(val) {
  form.caihong_enabled = val
}

async function handleSubmit() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    submitting.value = true
    try {
      const res = await updatePaymentConfig(form)
      if (res.code === 0) {
        ElMessage.success('配置保存成功')
      }
    } finally {
      submitting.value = false
    }
  })
}

onMounted(() => {
  fetchConfig()
})
</script>

<style scoped>
.payment-config {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
}

.config-card {
  max-width: 600px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: 600;
}
</style>
