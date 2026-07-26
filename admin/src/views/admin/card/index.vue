<template>
  <div class="admin-card">
    <div class="page-header">
      <h2>卡密管理</h2>
    </div>

    <el-card class="stats-card">
      <el-row :gutter="20">
        <el-col :span="4">
          <div class="stat-item">
            <div class="stat-value">{{ stats.total }}</div>
            <div class="stat-label">总数</div>
          </div>
        </el-col>
        <el-col :span="4">
          <div class="stat-item">
            <div class="stat-value text-info">{{ stats.unused }}</div>
            <div class="stat-label">未使用</div>
          </div>
        </el-col>
        <el-col :span="4">
          <div class="stat-item">
            <div class="stat-value text-success">{{ stats.activated }}</div>
            <div class="stat-label">已激活</div>
          </div>
        </el-col>
        <el-col :span="4">
          <div class="stat-item">
            <div class="stat-value text-warning">{{ stats.expired }}</div>
            <div class="stat-label">已到期</div>
          </div>
        </el-col>
        <el-col :span="4">
          <div class="stat-item">
            <div class="stat-value text-danger">{{ stats.banned }}</div>
            <div class="stat-label">已封禁</div>
          </div>
        </el-col>
        <el-col :span="4">
          <div class="stat-item">
            <div class="stat-value text-gray">{{ stats.voided }}</div>
            <div class="stat-label">已作废</div>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <el-card class="filter-card">
      <el-form :inline="true" :model="filterForm">
        <el-form-item label="商户ID">
          <el-input
            v-model="filterForm.merchant_id"
            placeholder="商户ID"
            clearable
            style="width: 120px"
            @keyup.enter="fetchList"
          />
        </el-form-item>
        <el-form-item label="应用">
          <el-select v-model="filterForm.app_id" placeholder="全部应用" clearable style="width: 160px">
          </el-select>
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="filterForm.card_type" placeholder="全部类型" clearable style="width: 120px">
            <el-option label="日卡" :value="1" />
            <el-option label="周卡" :value="2" />
            <el-option label="月卡" :value="3" />
            <el-option label="季卡" :value="4" />
            <el-option label="年卡" :value="5" />
            <el-option label="永久卡" :value="6" />
            <el-option label="试用卡" :value="7" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filterForm.status" placeholder="全部状态" clearable style="width: 120px">
            <el-option label="未使用" :value="1" />
            <el-option label="已激活" :value="2" />
            <el-option label="已到期" :value="3" />
            <el-option label="已封禁" :value="4" />
            <el-option label="已作废" :value="5" />
          </el-select>
        </el-form-item>
        <el-form-item label="前缀">
          <el-input
            v-model="filterForm.keyword"
            placeholder="卡密前缀"
            clearable
            style="width: 140px"
            @keyup.enter="fetchList"
          />
        </el-form-item>
        <el-form-item label="时间">
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
      <el-table :data="list" border>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="商户" width="100">
          <template #default="{ row }">
            <span>{{ row.merchant_id }}</span>
          </template>
        </el-table-column>
        <el-table-column label="应用" width="140">
          <template #default="{ row }">
            <span>{{ row.app?.name || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="card_no_prefix" label="前缀" width="100">
          <template #default="{ row }">
            <span class="prefix-text">{{ row.card_no_prefix || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="90" align="center">
          <template #default="{ row }">
            <el-tag size="small" type="info">{{ row.card_type_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag
              :type="getStatusTagType(row.status)"
              size="small"
            >
              {{ row.status_text }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="activate_time" label="激活时间" width="170">
          <template #default="{ row }">
            <span>{{ row.activate_time || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="expire_time" label="到期时间" width="170">
          <template #default="{ row }">
            <span>{{ row.expire_time || '永久' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="170" />
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="goDetail(row)">详情</el-button>
            <el-button
              v-if="row.status !== 4"
              type="warning"
              link
              @click="handleBan(row)"
            >
              封禁
            </el-button>
            <el-button
              v-if="row.status === 4"
              type="success"
              link
              @click="handleUnban(row)"
            >
              解封
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
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getCardList, banCard, unbanCard } from '@/api/admin/card'

const router = useRouter()
const loading = ref(false)
const list = ref([])

const stats = reactive({
  total: 0,
  unused: 0,
  activated: 0,
  expired: 0,
  banned: 0,
  voided: 0
})

const filterForm = reactive({
  merchant_id: '',
  app_id: '',
  card_type: '',
  status: '',
  keyword: ''
})

const dateRange = ref([])

const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

function getStatusTagType(status) {
  const types = {
    1: 'info',
    2: 'success',
    3: 'warning',
    4: 'danger',
    5: 'info'
  }
  return types[status] || 'info'
}

async function fetchList() {
  loading.value = true
  try {
    const params = {
      page: pagination.page,
      pageSize: pagination.pageSize,
      merchant_id: filterForm.merchant_id,
      app_id: filterForm.app_id,
      card_type: filterForm.card_type,
      status: filterForm.status,
      keyword: filterForm.keyword
    }
    if (dateRange.value && dateRange.value.length === 2) {
      params.start_time = dateRange.value[0] + ' 00:00:00'
      params.end_time = dateRange.value[1] + ' 23:59:59'
    }
    const res = await getCardList(params)
    if (res.code === 0) {
      list.value = res.data.list
      pagination.total = res.data.total
      if (res.data.stats) {
        Object.assign(stats, res.data.stats)
      }
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function resetFilter() {
  filterForm.merchant_id = ''
  filterForm.app_id = ''
  filterForm.card_type = ''
  filterForm.status = ''
  filterForm.keyword = ''
  dateRange.value = []
  pagination.page = 1
  fetchList()
}

function handleBan(row) {
  ElMessageBox.prompt('请输入封禁原因（可选）', '封禁卡密', {
    confirmButtonText: '确定封禁',
    cancelButtonText: '取消',
    inputPlaceholder: '请输入封禁原因',
    type: 'warning'
  }).then(async ({ value }) => {
    try {
      const res = await banCard(row.id, value || '')
      if (res.code === 0) {
        ElMessage.success('封禁成功')
        fetchList()
      }
    } catch (e) {
      console.error(e)
    }
  }).catch(() => {})
}

function handleUnban(row) {
  ElMessageBox.confirm(
    '确定要解封该卡密吗？',
    '提示',
    {
      type: 'warning',
      confirmButtonText: '确定',
      cancelButtonText: '取消'
    }
  ).then(async () => {
    try {
      const res = await unbanCard(row.id)
      if (res.code === 0) {
        ElMessage.success('解封成功')
        fetchList()
      }
    } catch (e) {
      console.error(e)
    }
  }).catch(() => {})
}

function goDetail(row) {
  router.push(`/admin/card/detail/${row.id}`)
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.admin-card {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
}

.stats-card {
  margin-bottom: 16px;
}

.stat-item {
  text-align: center;
  padding: 10px 0;
}

.stat-value {
  font-size: 24px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 4px;
}

.stat-value.text-info { color: #909399; }
.stat-value.text-success { color: #67c23a; }
.stat-value.text-warning { color: #e6a23c; }
.stat-value.text-danger { color: #f56c6c; }
.stat-value.text-gray { color: #c0c4cc; }

.stat-label {
  font-size: 14px;
  color: #909399;
}

.filter-card {
  margin-bottom: 16px;
}

.list-card {
  margin-bottom: 20px;
}

.prefix-text {
  font-family: monospace;
  font-weight: 600;
  color: #409eff;
}

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>
