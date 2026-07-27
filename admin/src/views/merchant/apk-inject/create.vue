<template>
  <div class="apk-inject-create">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>创建APK注入任务</span>
          <el-button @click="goBack">返回列表</el-button>
        </div>
      </template>

      <el-form :model="form" label-width="120px" style="max-width: 600px">
        <el-form-item label="选择应用" required>
          <el-select v-model="form.app_id" placeholder="请选择应用" filterable>
            <el-option v-for="app in apps" :key="app.id" :label="app.name" :value="app.id" />
          </el-select>
        </el-form-item>

        <el-form-item label="APK文件" required>
          <el-upload
            ref="uploadRef"
            :auto-upload="false"
            :limit="1"
            accept=".apk"
            :on-change="handleFileChange"
            :on-exceed="handleExceed"
          >
            <el-button type="primary">选择APK文件</el-button>
            <template #tip>
              <div class="el-upload__tip">仅支持 .apk 文件，最大 100MB</div>
            </template>
          </el-upload>
        </el-form-item>

        <el-form-item v-if="selectedFile" label="文件信息">
          <div>
            <div>文件名：{{ selectedFile.name }}</div>
            <div>大小：{{ formatSize(selectedFile.size) }}</div>
            <div v-if="sha256">SHA-256：{{ sha256 }}</div>
          </div>
        </el-form-item>

        <el-form-item v-if="uploading" label="上传进度">
          <el-progress :percentage="uploadProgress" :status="uploadProgress === 100 ? 'success' : ''" />
        </el-form-item>

        <el-form-item>
          <el-button type="primary" :loading="submitting" :disabled="!canSubmit" @click="handleSubmit">
            创建并上传
          </el-button>
          <el-button @click="goBack">取消</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { createInjectTask, dispatchInjectTask } from '@/api/merchant/apkInject'
import { getAppList } from '@/api/merchant/app'

const router = useRouter()
const uploadRef = ref()
const apps = ref([])
const selectedFile = ref(null)
const sha256 = ref('')
const submitting = ref(false)
const uploading = ref(false)
const uploadProgress = ref(0)

const form = ref({
  app_id: ''
})

const canSubmit = computed(() => {
  return form.value.app_id && selectedFile.value && sha256.value && !submitting.value
})

const fetchApps = async () => {
  try {
    const res = await getAppList({ page: 1, pageSize: 100 })
    apps.value = res.data.list || []
  } catch (e) {
    ElMessage.error('获取应用列表失败')
  }
}

const handleFileChange = async (file) => {
  selectedFile.value = file.raw
  sha256.value = ''

  if (file.raw.size > 100 * 1024 * 1024) {
    ElMessage.error('文件大小超过100MB限制')
    selectedFile.value = null
    return
  }

  // 计算 SHA-256
  try {
    const buffer = await file.raw.arrayBuffer()
    const hashBuffer = await crypto.subtle.digest('SHA-256', buffer)
    const hashArray = Array.from(new Uint8Array(hashBuffer))
    sha256.value = hashArray.map(b => b.toString(16).padStart(2, '0')).join('')
  } catch (e) {
    ElMessage.error('计算文件哈希失败')
  }
}

const handleExceed = () => {
  ElMessage.warning('只能上传一个文件')
}

const handleSubmit = async () => {
  submitting.value = true
  try {
    // 1. 创建任务，获取 presigned URL
    const createRes = await createInjectTask({
      app_id: form.value.app_id,
      filename: selectedFile.value.name,
      file_size: selectedFile.value.size,
      sha256: sha256.value
    })

    const { task_id, upload_url } = createRes.data

    // 2. 直传到 MinIO
    uploading.value = true
    await uploadToMinio(upload_url, selectedFile.value)
    uploading.value = false

    // 3. 通知后端投递队列
    await dispatchInjectTask(task_id)

    ElMessage.success('任务已提交，正在处理中')
    router.push('/apk-inject')
  } catch (e) {
    ElMessage.error(e.message || '提交失败')
  } finally {
    submitting.value = false
    uploading.value = false
  }
}

const uploadToMinio = (url, file) => {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open('PUT', url, true)
    xhr.setRequestHeader('Content-Type', 'application/vnd.android.package-archive')

    xhr.upload.onprogress = (e) => {
      if (e.lengthComputable) {
        uploadProgress.value = Math.round((e.loaded / e.total) * 100)
      }
    }

    xhr.onload = () => {
      if (xhr.status === 200) {
        resolve()
      } else {
        reject(new Error('上传失败: ' + xhr.status))
      }
    }

    xhr.onerror = () => reject(new Error('网络错误'))
    xhr.send(file)
  })
}

const goBack = () => {
  router.push('/apk-inject')
}

const formatSize = (bytes) => {
  if (!bytes) return '-'
  return (bytes / 1024 / 1024).toFixed(2) + ' MB'
}

onMounted(fetchApps)
</script>

<style scoped>
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
