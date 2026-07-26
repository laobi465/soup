<template>
  <div class="agent-commission">
    <div class="page-header">
      <h2>佣金明细</h2>
    </div>

    <div class="stats-bar">
      <div class="stat-item">
        <div class="stat-label">累计佣金</div>
        <div class="stat-value total">¥{{ stats?.total || '0.00' }}</div>
      </div>
      <div class="stat-item">
        <div class="stat-label">已结算</div>
        <div class="stat-value settled">¥{{ stats?.settled || '0.00' }}</div>
      </div>
      <div class="stat-item">
        <div class="stat-label">待结算</div>
        <div class="stat-value pending">¥{{ stats?.pending || '0.00' }}</div>
      </div>
    </div>

    <div class="search-bar">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="结算状态">
          <el-select v-model="searchForm.settle_status" placeholder="全部" clearable style="width: 140px">
            <el-option label="待结算" :value="0" />
            <el-option label="已结算" :value="1" />
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
      <el-table-column prop="type_text" label="类型" width="100">
        <template #default="{ row }">
          <el-tag :type="row.type === 5 ? 'success' : 'warning'" size="small">
            {{ row.type_text }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="amount" label="金额" width="120">
        <template #default="{ row }">
          <span :class="row.type === 5 ? 'income' : 'expense'">
            {{ row.type === 5 ? '+' : '-' }}¥{{ row.amount }}
          </span>
        </template>
      </el-table-column>
      <el-table-column prop="related_order" label="关联订单" width="200" />
      <el-table-column prop="settle_status_text" label="结算状态" width="100">
        <template #default="{ row }">
          <el-tag size="small" :type="row.settle_status === 1 ? 'success' : 'warning'">
            {{ row.settle_status_text }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="settle_date" label="结算日期" width="120" />
      <el-table-column prop="remark" label="备注" min-width="150" />
      <el-table-column prop="created_at" label="时间" width="180" />
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
import { getAgentCommission } from '@/api/agent'

const loading = ref(false)
const tableData = ref([])
const stats = ref(null)

const searchForm = reactive({
  settle_status: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

async function fetchList() {
  loading.value = true
  try {
    const res = await getAgentCommission({
      page: pagination.page,
      pageSize: pagination.pageSize,
      settle_status: searchForm.settle_status
    })
    if (res.code === 0) {
      tableData.value = res.data.list
      pagination.total = res.data.total
      stats.value = res.data.stats
    }
  } finally {
    loading.value = false
  }
}

function resetSearch() {
  searchForm.settle_status = ''
  pagination.page = 1
  fetchList()
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.agent-commission {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
}

.stats-bar {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-bottom: 20px;
}

.stat-item {
  background: white;
  padding: 20px;
  border-radius: 8px;
  text-align: center;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.stat-label {
  font-size: 14px;
  color: #909399;
  margin-bottom: 8px;
}

.stat-value {
  font-size: 24px;
  font-weight: 600;
}

.stat-value.total {
  color: #409eff;
}

.stat-value.settled {
  color: #67c23a;
}

.stat-value.pending {
  color: #e6a23c;
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

.income {
  color: #67c23a;
  font-weight: 500;
}

.expense {
  color: #f56c6c;
  font-weight: 500;
}
</style>
