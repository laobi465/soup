<template>
  <div class="admin-app">
    <div class="page-header">
      <h2>应用管理</h2>
    </div>

    <el-card class="filter-card">
      <el-form :inline="true" :model="filterForm">
        <el-form-item label="关键词">
          <el-input
            v-model="filterForm.keyword"
            placeholder="应用名称/AppKey"
            clearable
            @keyup.enter="fetchList"
          />
        </el-form-item>
        <el-form-item label="商户ID">
          <el-input
            v-model="filterForm.merchant_id"
            placeholder="商户ID"
            clearable
            style="width: 120px"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filterForm.status" placeholder="全部" clearable style="width: 120px">
            <el-option label="启用" :value="1" />
            <el-option label="停用" :value="0" />
          </el-select>
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
        <el-table-column label="应用" min-width="200">
          <template #default="{ row }">
            <div class="app-info">
              <div class="app-icon" v-if="row.icon">
                <img :src="row.icon" alt="" />
              </div>
              <div class="app-icon default-icon" v-else>
                <el-icon><Monitor /></el-icon>
              </div>
              <div class="app-detail">
                <div class="app-name">{{ row.name }}</div>
                <div class="app-version">v{{ row.version || '-' }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="merchant_name" label="所属商户" min-width="150" />
        <el-table-column prop="app_key" label="AppKey" min-width="200">
          <template #default="{ row }">
            <div class="app-key">
              <span>{{ row.app_key }}</span>
              <el-button type="text" @click="copyText(row.app_key)">
                <el-icon><CopyDocument /></el-icon>
              </el-button>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'">
              {{ row.status === 1 ? '启用' : '停用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="goDetail(row)">详情</el-button>
            <el-button
              :type="row.status === 1 ? 'warning' : 'success'"
              link
              @click="handleToggleStatus(row)"
            >
              {{ row.status === 1 ? '停用' : '启用' }}
            </el-button>
            <el-button type="danger" link @click="handleDelete(row)">删除</el-button>
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
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Monitor, CopyDocument } from '@element-plus/icons-vue'
import {
  getAppList,
  updateAppStatus,
  deleteApp
} from '@/api/admin/app'

const router = useRouter()
const loading = ref(false)
const list = ref([])

const filterForm = reactive({
  keyword: '',
  merchant_id: '',
  status: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

async function fetchList() {
  loading.value = true
  try {
    const res = await getAppList({
      page: pagination.page,
      pageSize: pagination.pageSize,
      keyword: filterForm.keyword,
      merchant_id: filterForm.merchant_id,
      status: filterForm.status
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

function resetFilter() {
  filterForm.keyword = ''
  filterForm.merchant_id = ''
  filterForm.status = ''
  pagination.page = 1
  fetchList()
}

function handleToggleStatus(row) {
  const action = row.status === 1 ? '停用' : '启用'
  ElMessageBox.confirm(
    `确定要${action}应用「${row.name}」吗？`,
    '提示',
    {
      type: 'warning',
      confirmButtonText: '确定',
      cancelButtonText: '取消'
    }
  ).then(async () => {
    try {
      const res = await updateAppStatus(row.id, row.status === 1 ? 0 : 1)
      if (res.code === 0) {
        ElMessage.success(`${action}成功`)
        fetchList()
      }
    } catch (e) {
      console.error(e)
    }
  }).catch(() => {})
}

function handleDelete(row) {
  ElMessageBox.confirm(
    `确定要删除应用「${row.name}」吗？删除后将无法恢复。`,
    '警告',
    {
      type: 'warning',
      confirmButtonText: '确定',
      cancelButtonText: '取消'
    }
  ).then(async () => {
    try {
      const res = await deleteApp(row.id)
      if (res.code === 0) {
        ElMessage.success('删除成功')
        fetchList()
      }
    } catch (e) {
      console.error(e)
    }
  }).catch(() => {})
}

function goDetail(row) {
  router.push(`/app/detail/${row.id}`)
}

function copyText(text) {
  navigator.clipboard.writeText(text).then(() => {
    ElMessage.success('已复制到剪贴板')
  }).catch(() => {
    ElMessage.error('复制失败')
  })
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.admin-app {
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

.app-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.app-icon {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  overflow: hidden;
  background: #f5f7fa;
  display: flex;
  align-items: center;
  justify-content: center;
}

.app-icon img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.app-icon.default-icon {
  font-size: 24px;
  color: #909399;
}

.app-detail .app-name {
  font-weight: 500;
  color: #303133;
  font-size: 14px;
}

.app-detail .app-version {
  font-size: 12px;
  color: #909399;
  margin-top: 2px;
}

.app-key {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: monospace;
  font-size: 13px;
  color: #606266;
}

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>
