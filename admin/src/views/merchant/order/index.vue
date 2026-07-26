<template>
  <div class="merchant-order">
    <div class="page-header">
      <h2>订单管理</h2>
    </div>

    <div class="search-bar">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="订单号">
          <el-input v-model="searchForm.keyword" placeholder="请输入订单号" clearable @keyup.enter="fetchList" />
        </el-form-item>
        <el-form-item label="订单类型">
          <el-select v-model="searchForm.type" placeholder="全部" clearable style="width: 140px">
            <el-option label="套餐购买" :value="1" />
            <el-option label="发卡商品" :value="2" />
            <el-option label="余额充值" :value="3" />
            <el-option label="套餐续费" :value="4" />
          </el-select>
        </el-form-item>
        <el-form-item label="支付状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 120px">
            <el-option label="待支付" :value="1" />
            <el-option label="已支付" :value="2" />
            <el-option label="已关闭" :value="3" />
            <el-option label="已退款" :value="4" />
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
      <el-table-column prop="order_no" label="订单号" width="200" />
      <el-table-column prop="type_text" label="类型" width="100" />
      <el-table-column prop="amount" label="金额" width="100">
        <template #default="{ row }">
          ¥{{ row.amount }}
        </template>
      </el-table-column>
      <el-table-column prop="pay_channel_text" label="支付方式" width="100" />
      <el-table-column prop="status_text" label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="getStatusTagType(row.pay_status)">{{ row.status_text }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="pay_time" label="支付时间" width="180" />
      <el-table-column prop="created_at" label="创建时间" width="180" />
      <el-table-column label="操作" width="150" fixed="right">
        <template #default="{ row }">
          <el-button type="primary" link @click="handleDetail(row)">详情</el-button>
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

    <el-dialog v-model="detailVisible" title="订单详情" width="600px">
      <el-descriptions v-if="currentOrder" :column="2" border>
        <el-descriptions-item label="订单号">{{ currentOrder.order_no }}</el-descriptions-item>
        <el-descriptions-item label="订单类型">{{ currentOrder.type_text }}</el-descriptions-item>
        <el-descriptions-item label="商品名称">{{ currentOrder.product_name }}</el-descriptions-item>
        <el-descriptions-item label="订单金额">¥{{ currentOrder.amount }}</el-descriptions-item>
        <el-descriptions-item label="支付方式">{{ currentOrder.pay_channel_text }}</el-descriptions-item>
        <el-descriptions-item label="支付状态">
          <el-tag :type="getStatusTagType(currentOrder.pay_status)">
            {{ currentOrder.status_text }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="联系邮箱">{{ currentOrder.email }}</el-descriptions-item>
        <el-descriptions-item label="卡密">{{ currentOrder.card_no || '-' }}</el-descriptions-item>
        <el-descriptions-item label="创建时间" :span="2">{{ currentOrder.created_at }}</el-descriptions-item>
        <el-descriptions-item label="支付时间" :span="2">{{ currentOrder.pay_time || '-' }}</el-descriptions-item>
      </el-descriptions>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getOrderList, getOrderDetail } from '@/api/merchant/order'

const loading = ref(false)
const tableData = ref([])
const detailVisible = ref(false)
const currentOrder = ref(null)

const searchForm = reactive({
  keyword: '',
  type: '',
  status: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

function getStatusTagType(status) {
  const map = {
    1: 'warning',
    2: 'success',
    3: 'info',
    4: 'danger'
  }
  return map[status] || 'info'
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getOrderList({
      page: pagination.page,
      pageSize: pagination.pageSize,
      keyword: searchForm.keyword,
      type: searchForm.type,
      status: searchForm.status
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
  searchForm.keyword = ''
  searchForm.type = ''
  searchForm.status = ''
  pagination.page = 1
  fetchList()
}

async function handleDetail(row) {
  try {
    const res = await getOrderDetail(row.id)
    if (res.code === 0) {
      currentOrder.value = res.data
      detailVisible.value = true
    }
  } catch (e) {
    console.error(e)
  }
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.merchant-order {
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
