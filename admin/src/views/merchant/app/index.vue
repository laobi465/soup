<template>
  <div class="merchant-app">
    <div class="page-header">
      <h2>应用管理</h2>
      <el-button type="primary" @click="handleCreate">
        <el-icon><Plus /></el-icon>
        新建应用
      </el-button>
    </div>

    <el-card class="stats-card">
      <el-row :gutter="20">
        <el-col :span="8">
          <div class="stat-item">
            <div class="stat-value">{{ stats.total }}</div>
            <div class="stat-label">总应用数</div>
          </div>
        </el-col>
        <el-col :span="8">
          <div class="stat-item">
            <div class="stat-value text-success">{{ stats.enabled }}</div>
            <div class="stat-label">启用中</div>
          </div>
        </el-col>
        <el-col :span="8">
          <div class="stat-item">
            <div class="stat-value text-danger">{{ stats.disabled }}</div>
            <div class="stat-label">已停用</div>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <el-card class="filter-card">
      <el-form :inline="true" :model="filterForm">
        <el-form-item label="关键词">
          <el-input
            v-model="filterForm.keyword"
            placeholder="应用名称/AppKey"
            clearable
            @keyup.enter="fetchList"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filterForm.status" placeholder="全部" clearable style="width: 120px">
            <el-option label="启用" :value="1" />
            <el-option label="停用" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetchList">搜索</el-button>
          <el-button @click="resetFilter">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card v-loading="loading" class="list-card">
      <el-table :data="list" border>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="应用" min-width="200">
          <template #default="{ row }">
            <div class="app-info">
              <div class="app-icon" v-if="row.icon">
                <img :src="row.icon" alt="" />
              </div>
              <div class="app-icon default-icon" v-else>
                <el-icon><Monitor /></el-icon>
              </div>
              <div class="app-detail">
                <div class="app-name">{{ row.name }}</div>
                <div class="app-version">v{{ row.version || '-' }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="app_key" label="AppKey" min-width="200">
          <template #default="{ row }">
            <div class="app-key">
              <span>{{ row.app_key }}</span>
              <el-button type="text" @click="copyText(row.app_key)">
                <el-icon><CopyDocument /></el-icon>
              </el-button>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="卡密数" width="100" align="center">
          <template #default="{ row }">
            <span class="card-count">{{ row.card_count || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'">
              {{ row.status === 1 ? '启用' : '停用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="操作" width="220" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="goDetail(row)">详情</el-button>
            <el-button type="primary" link @click="handleEdit(row)">编辑</el-button>
            <el-button
              :type="row.status === 1 ? 'warning' : 'success'"
              link
              @click="handleToggleStatus(row)"
            >
              {{ row.status === 1 ? '停用' : '启用' }}
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.pageSize"
          :total="pagination.total"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="fetchList"
          @current-change="fetchList"
        />
      </div>
    </el-card>

    <el-dialog
      v-model="dialogVisible"
      :title="isEdit ? '编辑应用' : '新建应用'"
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
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="secretDialogVisible"
      title="应用密钥"
      width="500px"
      @close="secretDialogVisible = false"
    >
      <div class="secret-tip">
        <el-alert
          title="请妥善保管您的AppSecret，它只会显示一次！"
          type="warning"
          :closable="false"
          show-icon
        />
      </div>
      <div class="secret-content">
        <div class="secret-item">
          <span class="label">AppKey：</span>
          <span class="value">{{ currentApp?.app_key }}</span>
        </div>
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
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox, ElNotification } from 'element-plus'
import { Plus, Monitor, CopyDocument } from '@element-plus/icons-vue'
import {
  getAppList,
  createApp,
  updateApp,
  updateAppStatus
} from '@/api/merchant/app'

const router = useRouter()
const loading = ref(false)
const submitLoading = ref(false)
const dialogVisible = ref(false)
const secretDialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref(null)
const list = ref([])
const currentApp = ref(null)
const newSecret = ref('')

const stats = reactive({
  total: 0,
  enabled: 0,
  disabled: 0
})

const filterForm = reactive({
  keyword: '',
  status: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

const form = reactive({
  id: null,
  name: '',
  icon: '',
  version: '',
  description: '',
  bind_limit: 1,
  ip_whitelist: ''
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

async function fetchList() {
  loading.value = true
  try {
    const res = await getAppList({
      page: pagination.page,
      pageSize: pagination.pageSize,
      keyword: filterForm.keyword,
      status: filterForm.status
    })
    if (res.code === 0) {
      list.value = res.data.list
      pagination.total = res.data.total
      if (res.data.stats) {
        stats.total = res.data.stats.total
        stats.enabled = res.data.stats.enabled
        stats.disabled = res.data.stats.disabled
      }
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function resetFilter() {
  filterForm.keyword = ''
  filterForm.status = ''
  pagination.page = 1
  fetchList()
}

function resetForm() {
  form.id = null
  form.name = ''
  form.icon = ''
  form.version = ''
  form.description = ''
  form.bind_limit = 1
  form.ip_whitelist = ''
  formRef.value?.clearValidate()
}

function handleCreate() {
  isEdit.value = false
  resetForm()
  dialogVisible.value = true
}

function handleEdit(row) {
  isEdit.value = true
  form.id = row.id
  form.name = row.name
  form.icon = row.icon || ''
  form.version = row.version || ''
  form.description = row.description || ''
  form.bind_limit = row.bind_limit
  form.ip_whitelist = Array.isArray(row.ip_whitelist) ? row.ip_whitelist.join('\n') : (row.ip_whitelist || '')
  dialogVisible.value = true
}

function handleSubmit() {
  formRef.value.validate(async (valid) => {
    if (!valid) return

    submitLoading.value = true
    try {
      let res
      if (isEdit.value) {
        res = await updateApp(form.id, form)
      } else {
        res = await createApp(form)
      }

      if (res.code === 0) {
        ElMessage.success(isEdit.value ? '更新成功' : '创建成功')
        dialogVisible.value = false

        if (!isEdit.value && res.data.app_secret) {
          currentApp.value = res.data
          newSecret.value = res.data.app_secret
          secretDialogVisible.value = true
        }

        fetchList()
      }
    } finally {
      submitLoading.value = false
    }
  })
}

function handleToggleStatus(row) {
  const action = row.status === 1 ? '停用' : '启用'
  ElMessageBox.confirm(
    `确定要${action}应用「${row.name}」吗？${row.status === 1 ? '停用后所有该应用的卡密验证将失效。' : ''}`,
    '提示',
    {
      type: 'warning',
      confirmButtonText: '确定',
      cancelButtonText: '取消'
    }
  ).then(async () => {
    try {
      const res = await updateAppStatus(row.id, row.status === 1 ? 0 : 1)
      if (res.code === 0) {
        ElMessage.success(`${action}成功`)
        fetchList()
      }
    } catch (e) {
      console.error(e)
    }
  }).catch(() => {})
}

function goDetail(row) {
  router.push(`/app/detail/${row.id}`)
}

function copyText(text) {
  navigator.clipboard.writeText(text).then(() => {
    ElMessage.success('已复制到剪贴板')
  }).catch(() => {
    ElMessage.error('复制失败')
  })
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.merchant-app {
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

.stats-card {
  margin-bottom: 16px;
}

.stat-item {
  text-align: center;
  padding: 10px 0;
}

.stat-value {
  font-size: 28px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 4px;
}

.stat-value.text-success {
  color: #67c23a;
}

.stat-value.text-danger {
  color: #f56c6c;
}

.stat-label {
  font-size: 14px;
  color: #909399;
}

.filter-card {
  margin-bottom: 16px;
}

.list-card {
  margin-bottom: 20px;
}

.app-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.app-icon {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  overflow: hidden;
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
  font-size: 24px;
  color: #909399;
}

.app-detail .app-name {
  font-weight: 500;
  color: #303133;
  font-size: 14px;
}

.app-detail .app-version {
  font-size: 12px;
  color: #909399;
  margin-top: 2px;
}

.app-key {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: monospace;
  font-size: 13px;
  color: #606266;
}

.card-count {
  font-weight: 500;
  color: #409eff;
}

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.tip-text {
  margin-left: 10px;
  font-size: 12px;
  color: #909399;
}

.secret-tip {
  margin-bottom: 20px;
}

.secret-content {
  padding: 20px;
  background: #f5f7fa;
  border-radius: 8px;
}

.secret-item {
  display: flex;
  align-items: center;
  margin-bottom: 12px;
  word-break: break-all;
}

.secret-item:last-child {
  margin-bottom: 0;
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
