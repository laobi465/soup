<template>
  <div class="log-operation">
    <div class="page-header">
      <h2>操作日志</h2>
    </div>

    <el-card class="filter-card">
      <el-form :inline="true" :model="filterForm">
        <el-form-item label="用户ID">
          <el-input
            v-model="filterForm.user_id"
            placeholder="用户ID"
            clearable
            style="width: 120px"
          />
        </el-form-item>
        <el-form-item label="操作">
          <el-input
            v-model="filterForm.action"
            placeholder="操作名称"
            clearable
            style="width: 150px"
          />
        </el-form-item>
        <el-form-item label="目标类型">
          <el-input
            v-model="filterForm.target_type"
            placeholder="目标类型"
            clearable
            style="width: 120px"
          />
        </el-form-item>
        <el-form-item label="IP">
          <el-input
            v-model="filterForm.ip"
            placeholder="IP地址"
            clearable
            style="width: 140px"
          />
        </el-form-item>
        <el-form-item label="时间范围">
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
      <el-table :data="list" border stripe>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="username" label="用户名" width="120" />
        <el-table-column prop="action" label="操作" width="180" />
        <el-table-column prop="target_type" label="目标类型" width="100" />
        <el-table-column prop="target_id" label="目标ID" width="100" />
        <el-table-column prop="ip" label="IP地址" width="140" />
        <el-table-column prop="request_data" label="请求数据" min-width="200">
          <template #default="{ row }">
            <el-tooltip
              v-if="row.request_data"
              :content="row.request_data"
              placement="top"
            >
              <span class="truncate-text">{{ row.request_data }}</span>
            </el-tooltip>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="操作时间" width="180" />
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
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getOperationLogs } from '@/api/admin/log'

const loading = ref(false)
const list = ref([])
const dateRange = ref([])

const filterForm = reactive({
  user_id: '',
  action: '',
  target_type: '',
  ip: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

async function fetchList() {
  loading.value = true
  try {
    const params = {
      page: pagination.page,
      pageSize: pagination.pageSize,
      user_id: filterForm.user_id,
      action: filterForm.action,
      target_type: filterForm.target_type,
      ip: filterForm.ip
    }

    if (dateRange.value && dateRange.value.length === 2) {
      params.start_time = dateRange.value[0] + ' 00:00:00'
      params.end_time = dateRange.value[1] + ' 23:59:59'
    }

    const res = await getOperationLogs(params)
    if (res.code === 0) {
      list.value = res.data.list
      pagination.total = res.data.total
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function resetFilter() {
  filterForm.user_id = ''
  filterForm.action = ''
  filterForm.target_type = ''
  filterForm.ip = ''
  dateRange.value = []
  pagination.page = 1
  fetchList()
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.log-operation {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
}

.filter-card {
  margin-bottom: 16px;
}

.list-card {
  margin-bottom: 20px;
}

.truncate-text {
  display: inline-block;
  max-width: 300px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  vertical-align: middle;
}

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>
