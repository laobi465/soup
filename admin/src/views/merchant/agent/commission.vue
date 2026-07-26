<template>
  <div class="merchant-commission">
    <div class="page-header">
      <h2>佣金明细</h2>
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
          <span :class="row.type === 5 ? 'income' : 'frozen'">
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
import { getAgentCommissionList } from '@/api/merchant/agent'

const loading = ref(false)
const tableData = ref([])

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
    const res = await getAgentCommissionList({
      page: pagination.page,
      pageSize: pagination.pageSize,
      settle_status: searchForm.settle_status
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
  searchForm.settle_status = ''
  pagination.page = 1
  fetchList()
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.merchant-commission {
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

.income {
  color: #67c23a;
  font-weight: 500;
}

.frozen {
  color: #e6a23c;
  font-weight: 500;
}
</style>
