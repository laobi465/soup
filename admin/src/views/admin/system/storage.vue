<template>
  <div class="storage-config">
    <div class="page-header">
      <h2>存储配置</h2>
    </div>

    <div v-loading="loading" class="config-content">
      <el-card class="config-card">
        <template #header>
          <div class="card-header">
            <span>存储驱动设置</span>
          </div>
        </template>

        <el-form :model="form" :rules="rules" ref="formRef" label-width="140px">
          <el-form-item label="存储驱动" prop="storage_driver">
            <el-radio-group v-model="form.storage_driver">
              <el-radio label="local">本地存储</el-radio>
              <el-radio label="oss">阿里云OSS</el-radio>
              <el-radio label="cos">腾讯云COS</el-radio>
              <el-radio label="qiniu">七牛云</el-radio>
              <el-radio label="minio">MinIO</el-radio>
            </el-radio-group>
          </el-form-item>

          <el-divider content-position="left">上传设置</el-divider>

          <el-form-item label="最大上传大小" prop="upload_max_size">
            <el-input-number v-model="form.upload_max_size" :min="102400" :max="1073741824" :step="1048576" />
            <span style="margin-left: 10px; color: #999;">字节 (约 {{ (form.upload_max_size / 1024 / 1024).toFixed(2) }} MB)</span>
          </el-form-item>

          <el-form-item label="允许图片格式" prop="upload_image_exts">
            <el-input v-model="form.upload_image_exts" placeholder="jpg,jpeg,png,gif,bmp,webp" />
          </el-form-item>

          <el-form-item label="允许文件格式" prop="upload_file_exts">
            <el-input v-model="form.upload_file_exts" placeholder="zip,rar,7z,pdf,doc,docx,xls,xlsx" />
          </el-form-item>

          <el-divider content-position="left" v-if="form.storage_driver === 'local'">本地存储配置</el-divider>

          <template v-if="form.storage_driver === 'local'">
            <el-form-item label="基础URL" prop="storage_local_base_url">
              <el-input v-model="form.storage_local_base_url" placeholder="留空则使用当前域名" />
            </el-form-item>
          </template>

          <el-divider content-position="left" v-if="form.storage_driver === 'oss'">阿里云OSS配置</el-divider>

          <template v-if="form.storage_driver === 'oss'">
            <el-form-item label="AccessKey ID" prop="storage_oss_access_key_id">
              <el-input v-model="form.storage_oss_access_key_id" placeholder="请输入AccessKey ID" />
            </el-form-item>
            <el-form-item label="AccessKey Secret" prop="storage_oss_access_key_secret">
              <el-input v-model="form.storage_oss_access_key_secret" type="password" show-password placeholder="请输入AccessKey Secret" />
            </el-form-item>
            <el-form-item label="Bucket名称" prop="storage_oss_bucket">
              <el-input v-model="form.storage_oss_bucket" placeholder="请输入Bucket名称" />
            </el-form-item>
            <el-form-item label="Endpoint" prop="storage_oss_endpoint">
              <el-input v-model="form.storage_oss_endpoint" placeholder="例如：oss-cn-hangzhou.aliyuncs.com" />
            </el-form-item>
            <el-form-item label="地域" prop="storage_oss_region">
              <el-input v-model="form.storage_oss_region" placeholder="例如：oss-cn-hangzhou" />
            </el-form-item>
          </template>

          <el-divider content-position="left" v-if="form.storage_driver === 'cos'">腾讯云COS配置</el-divider>

          <template v-if="form.storage_driver === 'cos'">
            <el-form-item label="SecretId" prop="storage_cos_secret_id">
              <el-input v-model="form.storage_cos_secret_id" placeholder="请输入SecretId" />
            </el-form-item>
            <el-form-item label="SecretKey" prop="storage_cos_secret_key">
              <el-input v-model="form.storage_cos_secret_key" type="password" show-password placeholder="请输入SecretKey" />
            </el-form-item>
            <el-form-item label="Bucket名称" prop="storage_cos_bucket">
              <el-input v-model="form.storage_cos_bucket" placeholder="请输入Bucket名称" />
            </el-form-item>
            <el-form-item label="地域" prop="storage_cos_region">
              <el-input v-model="form.storage_cos_region" placeholder="例如：ap-beijing" />
            </el-form-item>
          </template>

          <el-divider content-position="left" v-if="form.storage_driver === 'qiniu'">七牛云配置</el-divider>

          <template v-if="form.storage_driver === 'qiniu'">
            <el-form-item label="AccessKey" prop="storage_qiniu_access_key">
              <el-input v-model="form.storage_qiniu_access_key" placeholder="请输入AccessKey" />
            </el-form-item>
            <el-form-item label="SecretKey" prop="storage_qiniu_secret_key">
              <el-input v-model="form.storage_qiniu_secret_key" type="password" show-password placeholder="请输入SecretKey" />
            </el-form-item>
            <el-form-item label="Bucket名称" prop="storage_qiniu_bucket">
              <el-input v-model="form.storage_qiniu_bucket" placeholder="请输入Bucket名称" />
            </el-form-item>
            <el-form-item label="域名" prop="storage_qiniu_domain">
              <el-input v-model="form.storage_qiniu_domain" placeholder="请输入访问域名，如：https://cdn.example.com" />
            </el-form-item>
          </template>

          <el-divider content-position="left" v-if="form.storage_driver === 'minio'">MinIO配置</el-divider>

          <template v-if="form.storage_driver === 'minio'">
            <el-form-item label="Endpoint" prop="storage_minio_endpoint">
              <el-input v-model="form.storage_minio_endpoint" placeholder="例如：minio.example.com:9000" />
            </el-form-item>
            <el-form-item label="AccessKey" prop="storage_minio_access_key">
              <el-input v-model="form.storage_minio_access_key" placeholder="请输入AccessKey" />
            </el-form-item>
            <el-form-item label="SecretKey" prop="storage_minio_secret_key">
              <el-input v-model="form.storage_minio_secret_key" type="password" show-password placeholder="请输入SecretKey" />
            </el-form-item>
            <el-form-item label="Bucket名称" prop="storage_minio_bucket">
              <el-input v-model="form.storage_minio_bucket" placeholder="请输入Bucket名称" />
            </el-form-item>
            <el-form-item label="启用SSL" prop="storage_minio_use_ssl">
              <el-switch v-model="form.storage_minio_use_ssl" :active-value="1" :inactive-value="0" />
            </el-form-item>
          </template>
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
import { getSystemConfig, saveSystemConfig } from '@/api/admin/config'

const loading = ref(false)
const submitting = ref(false)
const formRef = ref(null)

const form = reactive({
  storage_driver: 'local',
  upload_max_size: 10485760,
  upload_image_exts: 'jpg,jpeg,png,gif,bmp,webp',
  upload_file_exts: 'zip,rar,7z,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv',
  storage_local_base_url: '',
  storage_oss_access_key_id: '',
  storage_oss_access_key_secret: '',
  storage_oss_bucket: '',
  storage_oss_endpoint: '',
  storage_oss_region: '',
  storage_cos_secret_id: '',
  storage_cos_secret_key: '',
  storage_cos_bucket: '',
  storage_cos_region: '',
  storage_qiniu_access_key: '',
  storage_qiniu_secret_key: '',
  storage_qiniu_bucket: '',
  storage_qiniu_domain: '',
  storage_minio_endpoint: '',
  storage_minio_access_key: '',
  storage_minio_secret_key: '',
  storage_minio_bucket: '',
  storage_minio_use_ssl: 0
})

const rules = {
  storage_driver: [{ required: true, message: '请选择存储驱动', trigger: 'change' }]
}

async function fetchConfig() {
  loading.value = true
  try {
    const res = await getSystemConfig('storage')
    if (res.code === 0 && res.data && res.data.configs) {
      const configs = res.data.configs
      for (const key in configs) {
        if (form.hasOwnProperty(key)) {
          form[key] = configs[key]
        }
      }
    }
  } finally {
    loading.value = false
  }
}

async function handleSubmit() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    submitting.value = true
    try {
      const res = await saveSystemConfig(form)
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
.storage-config {
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
  max-width: 800px;
}

.card-header {
  font-weight: 600;
}
</style>
