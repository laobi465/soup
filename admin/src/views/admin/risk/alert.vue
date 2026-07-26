<template>
  <div class="risk-alerts">
    <div class="page-header">
      <h2>告警记录</h2>
    </div>

    <el-card v-loading="loading" class="list-card">
      <el-table :data="list" border stripe>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="级别" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getLevelTagType(row.level)">
              {{ getLevelText(row.level) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="200" />
        <el-table-column prop="content" label="内容" min-width="300" />
        <el-table-column prop="type" label="类型" width="120">
          <template #default="{ row }">
            {{ getTypeText(row.type) }}
          </template>
        </el-table-column>
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
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getRiskAlerts } from '@/api/admin/risk'

const loading = ref(false)
const list = ref([])

const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

function getLevelTagType(level) {
  const map = {
    danger: 'danger',
    warning: 'warning',
    info: 'info',
    success: 'success'
  }
  return map[level] || 'info'
}

function getLevelText(level) {
  const map = {
    danger: '严重',
    warning: '警告',
    info: '提示',
    success: '正常'
  }
  return map[level] || '未知'
}

function getTypeText(type) {
  const map = {
    register: '注册异常',
    order: '订单异常',
    api: 'API异常',
    login: '登录异常'
  }
  return map[type] || '其他'
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getRiskAlerts({
      page: pagination.page,
      pageSize: pagination.pageSize
    })
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

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.risk-alerts {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
}

.list-card {
  margin-bottom: 20px;
}

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>
