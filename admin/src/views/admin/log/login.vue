<template>
  <div class="log-login">
    <div class="page-header">
      <h2>登录日志</h2>
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
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.is_success ? 'success' : 'danger'">
              {{ row.is_success ? '成功' : '失败' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="ip" label="IP地址" width="140" />
        <el-table-column prop="user_agent" label="User Agent" min-width="200">
          <template #default="{ row }">
            <el-tooltip
              v-if="row.user_agent"
              :content="row.user_agent"
              placement="top"
            >
              <span class="truncate-text">{{ row.user_agent }}</span>
            </el-tooltip>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="登录时间" width="180" />
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
import { getLoginLogs } from '@/api/admin/log'

const loading = ref(false)
const list = ref([])
const dateRange = ref([])

const filterForm = reactive({
  user_id: '',
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
      ip: filterForm.ip
    }

    if (dateRange.value && dateRange.value.length === 2) {
      params.start_time = dateRange.value[0] + ' 00:00:00'
      params.end_time = dateRange.value[1] + ' 23:59:59'
    }

    const res = await getLoginLogs(params)
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
.log-login {
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
