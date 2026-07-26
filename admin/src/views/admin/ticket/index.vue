<template>
  <div class="admin-tickets">
    <div class="page-header">
      <h2>工单管理</h2>
    </div>

    <el-row :gutter="16" class="stats-row">
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-item">
            <div class="stat-value warning">{{ stats.pending || 0 }}</div>
            <div class="stat-label">待处理</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-item">
            <div class="stat-value primary">{{ stats.processing || 0 }}</div>
            <div class="stat-label">处理中</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-item">
            <div class="stat-value success">{{ stats.resolved || 0 }}</div>
            <div class="stat-label">已解决</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-item">
            <div class="stat-value info">{{ stats.closed || 0 }}</div>
            <div class="stat-label">已关闭</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card class="search-card">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" style="width: 130px" @change="fetchList">
            <el-option label="全部" value="" />
            <el-option label="待处理" :value="1" />
            <el-option label="处理中" :value="2" />
            <el-option label="已解决" :value="3" />
            <el-option label="已关闭" :value="4" />
          </el-select>
        </el-form-item>
        <el-form-item label="优先级">
          <el-select v-model="searchForm.priority" placeholder="全部" style="width: 130px" @change="fetchList">
            <el-option label="全部" value="" />
            <el-option label="低" :value="1" />
            <el-option label="中" :value="2" />
            <el-option label="高" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item label="关键词">
          <el-input v-model="searchForm.keyword" placeholder="搜索标题/工单号" clearable style="width: 200px" @keyup.enter="fetchList" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetchList">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card">
      <el-table :data="tableData" v-loading="loading" border stripe>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="ticket_no" label="工单号" width="160">
          <template #default="{ row }">
            <span class="ticket-no">{{ row.ticket_no }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="200" show-overflow-tooltip />
        <el-table-column prop="status_text" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusTagType(row.status)" size="small">{{ row.status_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="priority_text" label="优先级" width="90">
          <template #default="{ row }">
            <el-tag :type="getPriorityTagType(row.priority)" size="small">{{ row.priority_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="user_type_text" label="用户类型" width="90" />
        <el-table-column prop="user_id" label="用户ID" width="90" />
        <el-table-column prop="handler_id" label="处理人" width="90">
          <template #default="{ row }">
            {{ row.handler_id || '未分配' }}
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="操作" width="160" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="goDetail(row)">处理</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.pageSize"
        :page-sizes="[10, 15, 20, 50]"
        :total="pagination.total"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="fetchList"
        @current-change="fetchList"
        class="pagination"
      />
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getAdminTickets, getTicketStats } from '@/api/admin/ticket'

const router = useRouter()
const loading = ref(false)

const searchForm = reactive({
  status: '',
  priority: '',
  keyword: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 15,
  total: 0
})

const tableData = ref([])
const stats = ref({
  pending: 0,
  processing: 0,
  resolved: 0,
  closed: 0
})

function getStatusTagType(status) {
  const map = { 1: 'warning', 2: 'primary', 3: 'success', 4: 'info' }
  return map[status] || ''
}

function getPriorityTagType(priority) {
  const map = { 1: '', 2: 'warning', 3: 'danger' }
  return map[priority] || ''
}

async function fetchStats() {
  try {
    const res = await getTicketStats()
    if (res.code === 0) {
      stats.value = res.data
    }
  } catch (e) {
    console.error(e)
  }
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getAdminTickets({
      page: pagination.page,
      page_size: pagination.pageSize,
      status: searchForm.status,
      priority: searchForm.priority,
      keyword: searchForm.keyword
    })
    if (res.code === 0) {
      tableData.value = res.data.list
      pagination.total = res.data.total
    }
  } finally {
    loading.value = false
  }
}

function handleReset() {
  searchForm.status = ''
  searchForm.priority = ''
  searchForm.keyword = ''
  pagination.page = 1
  fetchList()
}

function goDetail(row) {
  router.push('/admin/ticket/' + row.id)
}

onMounted(() => {
  fetchStats()
  fetchList()
})
</script>

<style scoped>
.admin-tickets {
  padding: 20px;
}

.page-header h2 {
  margin: 0 0 16px 0;
  font-size: 20px;
}

.stats-row {
  margin-bottom: 16px;
}

.stat-card {
  text-align: center;
}

.stat-value {
  font-size: 28px;
  font-weight: 600;
  margin-bottom: 4px;
}

.stat-value.warning {
  color: #e6a23c;
}

.stat-value.primary {
  color: #409eff;
}

.stat-value.success {
  color: #67c23a;
}

.stat-value.info {
  color: #909399;
}

.stat-label {
  font-size: 14px;
  color: #909399;
}

.search-card {
  margin-bottom: 16px;
}

.table-card {
  background: #fff;
}

.ticket-no {
  font-family: monospace;
  font-size: 13px;
}

.pagination {
  margin-top: 16px;
  justify-content: flex-end;
}
</style>
