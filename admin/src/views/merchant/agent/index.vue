<template>
  <div class="merchant-agent">
    <div class="page-header">
      <h2>代理管理</h2>
    </div>

    <div class="search-bar">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="关键词">
          <el-input v-model="searchForm.keyword" placeholder="用户名/邮箱" clearable @keyup.enter="fetchList" />
        </el-form-item>
        <el-form-item label="等级">
          <el-select v-model="searchForm.level" placeholder="全部" clearable style="width: 120px">
            <el-option label="一级代理" :value="1" />
            <el-option label="二级代理" :value="2" />
            <el-option label="三级代理" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 120px">
            <el-option label="正常" :value="1" />
            <el-option label="禁用" :value="0" />
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
      <el-table-column prop="parent_username" label="上级" width="120" />
      <el-table-column prop="commission_rate" label="佣金比例" width="100">
        <template #default="{ row }">
          {{ (row.commission_rate * 100).toFixed(0) }}%
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
      <el-table-column label="操作" width="220" fixed="right">
        <template #default="{ row }">
          <el-button type="primary" link @click="handleDetail(row)">详情</el-button>
          <el-button type="warning" link @click="handleAdjust(row)">调整</el-button>
          <el-button
            :type="row.status === 1 ? 'danger' : 'success'"
            link
            @click="handleToggleStatus(row)"
          >
            {{ row.status === 1 ? '禁用' : '启用' }}
          </el-button>
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

    <el-dialog v-model="adjustVisible" title="调整代理等级" width="450px">
      <el-form :model="adjustForm" label-width="100px">
        <el-form-item label="佣金比例">
          <el-input-number v-model="adjustForm.commission_rate" :min="0" :max="1" :step="0.01" :precision="2" />
          <div class="form-tip">取值范围 0-1，例如 0.1 表示 10%</div>
        </el-form-item>
        <el-form-item label="拿货折扣">
          <el-input-number v-model="adjustForm.purchase_price_rate" :min="0" :max="1" :step="0.01" :precision="2" />
          <div class="form-tip">取值范围 0-1，例如 0.8 表示 8 折</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="adjustVisible = false">取消</el-button>
        <el-button type="primary" @click="submitAdjust" :loading="adjustLoading">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getAgentList, updateAgentLevel, updateAgentStatus } from '@/api/merchant/agent'

const router = useRouter()
const loading = ref(false)
const tableData = ref([])
const adjustVisible = ref(false)
const adjustLoading = ref(false)
const currentAgent = ref(null)

const searchForm = reactive({
  keyword: '',
  level: '',
  status: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

const adjustForm = reactive({
  commission_rate: 0,
  purchase_price_rate: 0
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
    const res = await getAgentList({
      page: pagination.page,
      pageSize: pagination.pageSize,
      keyword: searchForm.keyword,
      level: searchForm.level,
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
  searchForm.level = ''
  searchForm.status = ''
  pagination.page = 1
  fetchList()
}

function handleDetail(row) {
  router.push({ path: `/merchant/agent/detail/${row.id}` })
}

function handleAdjust(row) {
  currentAgent.value = row
  adjustForm.commission_rate = row.commission_rate
  adjustForm.purchase_price_rate = row.purchase_price_rate
  adjustVisible.value = true
}

async function submitAdjust() {
  if (!currentAgent.value) return
  adjustLoading.value = true
  try {
    const res = await updateAgentLevel(currentAgent.value.id, adjustForm)
    if (res.code === 0) {
      ElMessage.success('调整成功')
      adjustVisible.value = false
      fetchList()
    }
  } finally {
    adjustLoading.value = false
  }
}

function handleToggleStatus(row) {
  const action = row.status === 1 ? '禁用' : '启用'
  ElMessageBox.confirm(`确定要${action}代理「${row.username}」吗？`, '提示', {
    type: 'warning',
    confirmButtonText: '确定',
    cancelButtonText: '取消'
  }).then(async () => {
    const res = await updateAgentStatus(row.id, row.status === 1 ? 0 : 1)
    if (res.code === 0) {
      ElMessage.success(`${action}成功`)
      fetchList()
    }
  }).catch(() => {})
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.merchant-agent {
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

.form-tip {
  color: #909399;
  font-size: 12px;
  margin-top: 4px;
}
</style>
