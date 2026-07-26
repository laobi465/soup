<template>
  <div class="agent-team">
    <div class="page-header">
      <h2>我的团队</h2>
    </div>

    <div class="search-bar">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="等级">
          <el-select v-model="searchForm.level" placeholder="全部" clearable style="width: 120px">
            <el-option label="一级" :value="2" />
            <el-option label="二级" :value="3" />
            <el-option label="三级" :value="4" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetchList">搜索</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <el-table :data="tableData" v-loading="loading" border stripe>
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="username" label="用户名" width="140" />
      <el-table-column prop="email" label="邮箱" min-width="180" />
      <el-table-column prop="level_text" label="等级" width="100">
        <template #default="{ row }">
          <el-tag :type="getLevelTagType(row.level)">{{ row.level_text }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="total_earnings" label="累计收益" width="120">
        <template #default="{ row }">
          ¥{{ row.total_earnings }}
        </template>
      </el-table-column>
      <el-table-column prop="status_text" label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'">{{ row.status_text }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="加入时间" width="180" />
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
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getAgentTeam } from '@/api/agent'

const loading = ref(false)
const tableData = ref([])

const searchForm = reactive({
  level: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

function getLevelTagType(level) {
  const map = {
    1: 'success',
    2: 'warning',
    3: 'info'
  }
  return map[level] || 'info'
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getAgentTeam({
      page: pagination.page,
      pageSize: pagination.pageSize,
      level: searchForm.level
    })
    if (res.code === 0) {
      tableData.value = res.data.list
      pagination.total = res.data.total
    }
  } finally {
    loading.value = false
  }
}

function resetSearch() {
  searchForm.level = ''
  pagination.page = 1
  fetchList()
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.agent-team {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
}

.search-bar {
  margin-bottom: 20px;
  padding: 16px;
  background: #f5f7fa;
  border-radius: 4px;
}

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>
