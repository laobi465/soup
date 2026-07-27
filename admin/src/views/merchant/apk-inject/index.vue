<template>
  <div class="apk-inject-list">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>APK注入任务</span>
          <div>
            <el-button type="primary" @click="goCreate">创建注入任务</el-button>
            <el-button @click="fetchData">刷新</el-button>
          </div>
        </div>
      </template>

      <el-table :data="tableData" v-loading="loading" stripe>
        <el-table-column prop="task_no" label="任务编号" width="200" />
        <el-table-column prop="original_filename" label="文件名" min-width="180" show-overflow-tooltip />
        <el-table-column label="大小" width="100">
          <template #default="{ row }">
            {{ formatSize(row.file_size) }}
          </template>
        </el-table-column>
        <el-table-column label="状态" width="120">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)">{{ statusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="进度" width="150">
          <template #default="{ row }">
            <el-progress :percentage="row.progress" :status="row.status === 4 ? 'exception' : row.status === 3 ? 'success' : ''" />
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="showDetail(row)">详情</el-button>
            <el-button link type="primary" :disabled="row.status !== 3" @click="download(row)">下载</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="page"
        v-model:page-size="pageSize"
        :total="total"
        layout="total, prev, pager, next"
        @current-change="fetchData"
        class="pagination"
      />
    </el-card>

    <!-- 详情对话框 -->
    <el-dialog v-model="detailVisible" title="任务详情" width="600px">
      <el-descriptions :column="1" border v-if="detailData">
        <el-descriptions-item label="任务编号">{{ detailData.task_no }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="statusTag(detailData.status)">{{ statusText(detailData.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="进度">{{ detailData.progress }}%</el-descriptions-item>
        <el-descriptions-item label="文件名">{{ detailData.original_filename }}</el-descriptions-item>
        <el-descriptions-item label="文件大小">{{ formatSize(detailData.file_size) }}</el-descriptions-item>
        <el-descriptions-item label="SHA-256">{{ detailData.file_sha256 }}</el-descriptions-item>
        <el-descriptions-item label="创建时间">{{ detailData.created_at }}</el-descriptions-item>
        <el-descriptions-item label="完成时间">{{ detailData.completed_at || '-' }}</el-descriptions-item>
        <el-descriptions-item label="错误信息" v-if="detailData.error_log">
          <span style="color: #f56c6c">{{ detailData.error_log }}</span>
        </el-descriptions-item>
      </el-descriptions>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { getInjectTaskList, getInjectTaskDetail, getInjectDownloadUrl } from '@/api/merchant/apkInject'

const router = useRouter()
const loading = ref(false)
const tableData = ref([])
const page = ref(1)
const pageSize = ref(15)
const total = ref(0)
const detailVisible = ref(false)
const detailData = ref(null)
let pollTimer = null

const fetchData = async () => {
  loading.value = true
  try {
    const res = await getInjectTaskList({ page: page.value, page_size: pageSize.value })
    tableData.value = res.data.list || []
    total.value = res.data.total || 0

    // 如果有进行中的任务，启动轮询
    const hasProcessing = tableData.value.some(t => t.status === 1 || t.status === 2)
    if (hasProcessing && !pollTimer) {
      pollTimer = setInterval(fetchData, 5000)
    } else if (!hasProcessing && pollTimer) {
      clearInterval(pollTimer)
      pollTimer = null
    }
  } catch (e) {
    ElMessage.error('获取列表失败')
  } finally {
    loading.value = false
  }
}

const goCreate = () => {
  router.push('/apk-inject/create')
}

const showDetail = async (row) => {
  try {
    const res = await getInjectTaskDetail(row.id)
    detailData.value = res.data
    detailVisible.value = true
  } catch (e) {
    ElMessage.error('获取详情失败')
  }
}

const download = async (row) => {
  try {
    const res = await getInjectDownloadUrl(row.id)
    window.open(res.data.url, '_blank')
  } catch (e) {
    ElMessage.error('获取下载链接失败')
  }
}

const formatSize = (bytes) => {
  if (!bytes) return '-'
  const mb = bytes / 1024 / 1024
  return mb.toFixed(2) + ' MB'
}

const statusText = (status) => {
  return { 1: '排队中', 2: '处理中', 3: '已完成', 4: '失败' }[status] || '未知'
}

const statusTag = (status) => {
  return { 1: 'info', 2: 'warning', 3: 'success', 4: 'danger' }[status] || 'info'
}

onMounted(fetchData)
onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
})
</script>

<style scoped>
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.pagination {
  margin-top: 20px;
  justify-content: flex-end;
}
</style>
