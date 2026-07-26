<template>
  <div class="merchant-card">
    <div class="page-header">
      <h2>卡密管理</h2>
      <div class="header-actions">
        <el-button type="primary" @click="handleGenerate">
          <el-icon><Plus /></el-icon>
          生成卡密
        </el-button>
        <el-button @click="handleExport">
          <el-icon><Download /></el-icon>
          导出
        </el-button>
        <el-upload
          :show-file-list="false"
          :before-upload="handleImport"
          accept=".txt,.csv"
        >
          <el-button>
            <el-icon><Upload /></el-icon>
            导入
          </el-button>
        </el-upload>
      </div>
    </div>

    <el-card class="stats-card">
      <el-row :gutter="20">
        <el-col :span="4">
          <div class="stat-item">
            <div class="stat-value">{{ stats.total }}</div>
            <div class="stat-label">总数</div>
          </div>
        </el-col>
        <el-col :span="4">
          <div class="stat-item">
            <div class="stat-value text-info">{{ stats.unused }}</div>
            <div class="stat-label">未使用</div>
          </div>
        </el-col>
        <el-col :span="4">
          <div class="stat-item">
            <div class="stat-value text-success">{{ stats.activated }}</div>
            <div class="stat-label">已激活</div>
          </div>
        </el-col>
        <el-col :span="4">
          <div class="stat-item">
            <div class="stat-value text-warning">{{ stats.expired }}</div>
            <div class="stat-label">已到期</div>
          </div>
        </el-col>
        <el-col :span="4">
          <div class="stat-item">
            <div class="stat-value text-danger">{{ stats.banned }}</div>
            <div class="stat-label">已封禁</div>
          </div>
        </el-col>
        <el-col :span="4">
          <div class="stat-item">
            <div class="stat-value text-gray">{{ stats.voided }}</div>
            <div class="stat-label">已作废</div>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <el-card class="filter-card">
      <el-form :inline="true" :model="filterForm">
        <el-form-item label="应用">
          <el-select v-model="filterForm.app_id" placeholder="全部应用" clearable style="width: 160px">
            <el-option
              v-for="app in appList"
              :key="app.id"
              :label="app.name"
              :value="app.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="filterForm.card_type" placeholder="全部类型" clearable style="width: 120px">
            <el-option label="日卡" :value="1" />
            <el-option label="周卡" :value="2" />
            <el-option label="月卡" :value="3" />
            <el-option label="季卡" :value="4" />
            <el-option label="年卡" :value="5" />
            <el-option label="永久卡" :value="6" />
            <el-option label="试用卡" :value="7" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filterForm.status" placeholder="全部状态" clearable style="width: 120px">
            <el-option label="未使用" :value="1" />
            <el-option label="已激活" :value="2" />
            <el-option label="已到期" :value="3" />
            <el-option label="已封禁" :value="4" />
            <el-option label="已作废" :value="5" />
          </el-select>
        </el-form-item>
        <el-form-item label="前缀">
          <el-input
            v-model="filterForm.keyword"
            placeholder="卡密前缀"
            clearable
            style="width: 140px"
            @keyup.enter="fetchList"
          />
        </el-form-item>
        <el-form-item label="时间">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
          />
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
        <el-table-column label="应用" width="140">
          <template #default="{ row }">
            <span>{{ row.app?.name || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="card_no_prefix" label="前缀" width="100">
          <template #default="{ row }">
            <span class="prefix-text">{{ row.card_no_prefix || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="90" align="center">
          <template #default="{ row }">
            <el-tag size="small" type="info">{{ row.card_type_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag
              :type="getStatusTagType(row.status)"
              size="small"
            >
              {{ row.status_text }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="activate_time" label="激活时间" width="170">
          <template #default="{ row }">
            <span>{{ row.activate_time || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="expire_time" label="到期时间" width="170">
          <template #default="{ row }">
            <span>{{ row.expire_time || '永久' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="170" />
        <el-table-column label="操作" width="280" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="goDetail(row)">详情</el-button>
            <el-button
              v-if="row.status !== 4 && row.status !== 5"
              type="warning"
              link
              @click="handleBan(row)"
            >
              封禁
            </el-button>
            <el-button
              v-if="row.status === 4"
              type="success"
              link
              @click="handleUnban(row)"
            >
              解封
            </el-button>
            <el-button
              v-if="row.status !== 5"
              type="danger"
              link
              @click="handleVoid(row)"
            >
              作废
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
      v-model="generateDialogVisible"
      title="生成卡密"
      width="600px"
      @close="resetGenerateForm"
    >
      <el-form :model="generateForm" ref="generateFormRef" :rules="generateRules" label-width="100px">
        <el-form-item label="应用" prop="app_id">
          <el-select v-model="generateForm.app_id" placeholder="请选择应用" style="width: 100%">
            <el-option
              v-for="app in appList"
              :key="app.id"
              :label="app.name"
              :value="app.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="生成方式">
          <el-radio-group v-model="generateMode">
            <el-radio :value="1">单张生成</el-radio>
            <el-radio :value="2">批量生成</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="generateMode === 2" label="生成数量" prop="count">
          <el-input-number v-model="generateForm.count" :min="1" :max="1000" />
          <span class="tip-text">单次最多1000张</span>
        </el-form-item>
        <el-form-item label="卡密类型" prop="card_type">
          <el-select v-model="generateForm.card_type" placeholder="请选择类型" style="width: 100%">
            <el-option label="日卡（1天）" :value="1" />
            <el-option label="周卡（7天）" :value="2" />
            <el-option label="月卡（30天）" :value="3" />
            <el-option label="季卡（90天）" :value="4" />
            <el-option label="年卡（365天）" :value="5" />
            <el-option label="永久卡" :value="6" />
            <el-option label="试用卡（1天）" :value="7" />
          </el-select>
        </el-form-item>
        <el-form-item label="自定义时长">
          <el-input-number v-model="generateForm.duration" :min="0" :max="315360000" />
          <span class="tip-text">秒，留空则使用默认时长</span>
        </el-form-item>
        <el-form-item label="卡密前缀">
          <el-input v-model="generateForm.prefix" placeholder="如：VIP-" maxlength="20" />
        </el-form-item>
        <el-form-item label="卡密长度">
          <el-input-number v-model="generateForm.length" :min="16" :max="32" />
          <span class="tip-text">不含前缀，默认16位</span>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="generateForm.remark" placeholder="选填" maxlength="255" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="generateDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="generateLoading" @click="handleGenerateSubmit">
          生成
        </el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="resultDialogVisible"
      title="卡密生成结果"
      width="700px"
      :close-on-click-modal="false"
      @close="handleResultClose"
    >
      <div class="result-tip">
        <el-alert
          title="请妥善保管以下卡密，刷新页面后将无法再次查看！"
          type="warning"
          :closable="false"
          show-icon
        />
      </div>
      <div class="result-actions">
        <el-button type="primary" @click="copyAllCards">
          <el-icon><CopyDocument /></el-icon>
          复制全部
        </el-button>
        <el-button @click="exportToTxt">
          <el-icon><Download /></el-icon>
          导出TXT
        </el-button>
      </div>
      <div class="result-list">
        <div v-if="generateMode === 1" class="single-card">
          <el-input :model-value="resultCards[0]" readonly>
            <template #append>
              <el-button @click="copyText(resultCards[0])">复制</el-button>
            </template>
          </el-input>
        </div>
        <el-input
          v-else
          :model-value="resultCards.join('\n')"
          type="textarea"
          :rows="10"
          readonly
        />
      </div>
      <div class="result-info">
        共 {{ resultCards.length }} 张卡密
      </div>
      <template #footer>
        <el-button type="primary" @click="resultDialogVisible = false">我已保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Download, Upload, CopyDocument } from '@element-plus/icons-vue'
import {
  getCardList,
  generateCard,
  batchGenerateCard,
  banCard,
  unbanCard,
  voidCard,
  exportCards
} from '@/api/merchant/card'
import { getAppList } from '@/api/merchant/app'

const router = useRouter()
const loading = ref(false)
const generateLoading = ref(false)
const generateDialogVisible = ref(false)
const resultDialogVisible = ref(false)
const generateFormRef = ref(null)
const generateMode = ref(1)
const list = ref([])
const appList = ref([])
const resultCards = ref([])

const stats = reactive({
  total: 0,
  unused: 0,
  activated: 0,
  expired: 0,
  banned: 0,
  voided: 0
})

const filterForm = reactive({
  app_id: '',
  card_type: '',
  status: '',
  keyword: ''
})

const dateRange = ref([])

const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

const generateForm = reactive({
  app_id: null,
  count: 10,
  card_type: 3,
  duration: 0,
  prefix: '',
  length: 16,
  remark: ''
})

const generateRules = {
  app_id: [
    { required: true, message: '请选择应用', trigger: 'change' }
  ],
  card_type: [
    { required: true, message: '请选择卡密类型', trigger: 'change' }
  ],
  count: [
    { type: 'number', min: 1, max: 1000, message: '生成数量1-1000', trigger: 'blur' }
  ]
}

function getStatusTagType(status) {
  const types = {
    1: 'info',
    2: 'success',
    3: 'warning',
    4: 'danger',
    5: 'info'
  }
  return types[status] || 'info'
}

async function fetchAppList() {
  try {
    const res = await getAppList({ page: 1, pageSize: 100 })
    if (res.code === 0) {
      appList.value = res.data.list || []
    }
  } catch (e) {
    console.error(e)
  }
}

async function fetchList() {
  loading.value = true
  try {
    const params = {
      page: pagination.page,
      pageSize: pagination.pageSize,
      app_id: filterForm.app_id,
      card_type: filterForm.card_type,
      status: filterForm.status,
      keyword: filterForm.keyword
    }
    if (dateRange.value && dateRange.value.length === 2) {
      params.start_time = dateRange.value[0] + ' 00:00:00'
      params.end_time = dateRange.value[1] + ' 23:59:59'
    }
    const res = await getCardList(params)
    if (res.code === 0) {
      list.value = res.data.list
      pagination.total = res.data.total
      if (res.data.stats) {
        Object.assign(stats, res.data.stats)
      }
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function resetFilter() {
  filterForm.app_id = ''
  filterForm.card_type = ''
  filterForm.status = ''
  filterForm.keyword = ''
  dateRange.value = []
  pagination.page = 1
  fetchList()
}

function handleGenerate() {
  generateMode.value = 1
  resetGenerateForm()
  generateDialogVisible.value = true
}

function resetGenerateForm() {
  generateForm.app_id = null
  generateForm.count = 10
  generateForm.card_type = 3
  generateForm.duration = 0
  generateForm.prefix = ''
  generateForm.length = 16
  generateForm.remark = ''
  generateFormRef.value?.clearValidate()
}

function handleGenerateSubmit() {
  generateFormRef.value.validate(async (valid) => {
    if (!valid) return

    generateLoading.value = true
    try {
      let res
      if (generateMode.value === 1) {
        res = await generateCard(generateForm)
      } else {
        res = await batchGenerateCard(generateForm)
      }

      if (res.code === 0) {
        generateDialogVisible.value = false
        if (generateMode.value === 1) {
          resultCards.value = [res.data.card_no]
        } else {
          resultCards.value = res.data.cards || []
        }
        resultDialogVisible.value = true
        fetchList()
      }
    } finally {
      generateLoading.value = false
    }
  })
}

function handleResultClose() {
  ElMessageBox.confirm(
    '关闭后将无法再次查看卡密明文，确定已保存了吗？',
    '提示',
    {
      type: 'warning',
      confirmButtonText: '确定已保存',
      cancelButtonText: '再看看'
    }
  ).then(() => {
    resultDialogVisible.value = false
  }).catch(() => {})
}

function copyAllCards() {
  const text = resultCards.value.join('\n')
  copyText(text)
}

function exportToTxt() {
  const content = resultCards.value.join('\n')
  const blob = new Blob([content], { type: 'text/plain;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `cards_${Date.now()}.txt`
  link.click()
  URL.revokeObjectURL(url)
}

function copyText(text) {
  navigator.clipboard.writeText(text).then(() => {
    ElMessage.success('已复制到剪贴板')
  }).catch(() => {
    ElMessage.error('复制失败')
  })
}

function handleBan(row) {
  ElMessageBox.prompt('请输入封禁原因（可选）', '封禁卡密', {
    confirmButtonText: '确定封禁',
    cancelButtonText: '取消',
    inputPlaceholder: '请输入封禁原因',
    type: 'warning'
  }).then(async ({ value }) => {
    try {
      const res = await banCard(row.id, value || '')
      if (res.code === 0) {
        ElMessage.success('封禁成功')
        fetchList()
      }
    } catch (e) {
      console.error(e)
    }
  }).catch(() => {})
}

function handleUnban(row) {
  ElMessageBox.confirm(
    `确定要解封该卡密吗？`,
    '提示',
    {
      type: 'warning',
      confirmButtonText: '确定',
      cancelButtonText: '取消'
    }
  ).then(async () => {
    try {
      const res = await unbanCard(row.id)
      if (res.code === 0) {
        ElMessage.success('解封成功')
        fetchList()
      }
    } catch (e) {
      console.error(e)
    }
  }).catch(() => {})
}

function handleVoid(row) {
  ElMessageBox.confirm(
    '确定要作废该卡密吗？作废后无法恢复！',
    '警告',
    {
      type: 'warning',
      confirmButtonText: '确定作废',
      cancelButtonText: '取消',
      confirmButtonClass: 'el-button--danger'
    }
  ).then(async () => {
    try {
      const res = await voidCard(row.id)
      if (res.code === 0) {
        ElMessage.success('作废成功')
        fetchList()
      }
    } catch (e) {
      console.error(e)
    }
  }).catch(() => {})
}

function handleExport() {
  const params = {
    app_id: filterForm.app_id,
    card_type: filterForm.card_type,
    status: filterForm.status,
    keyword: filterForm.keyword
  }
  if (dateRange.value && dateRange.value.length === 2) {
    params.start_time = dateRange.value[0] + ' 00:00:00'
    params.end_time = dateRange.value[1] + ' 23:59:59'
  }
  exportCards(params).then((res) => {
    const blob = new Blob([res], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `cards_${Date.now()}.csv`
    link.click()
    URL.revokeObjectURL(url)
    ElMessage.success('导出成功')
  }).catch(() => {
    ElMessage.error('导出失败')
  })
}

function handleImport(file) {
  ElMessageBox.prompt('请选择应用和卡密类型', '导入卡密', {
    confirmButtonText: '确定导入',
    cancelButtonText: '取消',
    dangerouslyUseHTMLString: true,
    message: `
      <div style="margin-top: 10px;">
        <div style="margin-bottom: 10px;">应用：
          <select id="importAppId" style="width: 200px; padding: 4px;">
            <option value="">请选择应用</option>
            ${appList.value.map(a => `<option value="${a.id}">${a.name}</option>`).join('')}
          </select>
        </div>
        <div>类型：
          <select id="importCardType" style="width: 200px; padding: 4px;">
            <option value="1">日卡</option>
            <option value="2">周卡</option>
            <option value="3" selected>月卡</option>
            <option value="4">季卡</option>
            <option value="5">年卡</option>
            <option value="6">永久卡</option>
            <option value="7">试用卡</option>
          </select>
        </div>
      </div>
    `,
    beforeClose: (action, instance, done) => {
      if (action === 'confirm') {
        const appId = document.getElementById('importAppId').value
        const cardType = document.getElementById('importCardType').value
        if (!appId) {
          ElMessage.warning('请选择应用')
          return
        }
        instance.appId = appId
        instance.cardType = cardType
      }
      done()
    }
  }).then(async ({ action, appId, cardType }) => {
    if (action === 'confirm') {
      const formData = new FormData()
      formData.append('file', file)
      formData.append('app_id', appId)
      formData.append('card_type', cardType)
      try {
        const res = await importCards(formData)
        if (res.code === 0) {
          ElMessage.success(`导入完成：成功${res.data.success_count}张，失败${res.data.fail_count}张`)
          fetchList()
        }
      } catch (e) {
        console.error(e)
      }
    }
  }).catch(() => {})

  return false
}

function goDetail(row) {
  router.push(`/card/detail/${row.id}`)
}

onMounted(() => {
  fetchAppList()
  fetchList()
})
</script>

<style scoped>
.merchant-card {
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

.header-actions {
  display: flex;
  gap: 10px;
}

.stats-card {
  margin-bottom: 16px;
}

.stat-item {
  text-align: center;
  padding: 10px 0;
}

.stat-value {
  font-size: 24px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 4px;
}

.stat-value.text-info { color: #909399; }
.stat-value.text-success { color: #67c23a; }
.stat-value.text-warning { color: #e6a23c; }
.stat-value.text-danger { color: #f56c6c; }
.stat-value.text-gray { color: #c0c4cc; }

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

.prefix-text {
  font-family: monospace;
  font-weight: 600;
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

.result-tip {
  margin-bottom: 16px;
}

.result-actions {
  margin-bottom: 16px;
  display: flex;
  gap: 10px;
}

.result-list {
  margin-bottom: 10px;
}

.single-card {
  padding: 10px 0;
}

.result-info {
  text-align: right;
  color: #909399;
  font-size: 13px;
}
</style>
