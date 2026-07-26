<template>
  <div class="risk-blacklist">
    <div class="page-header">
      <h2>黑名单管理</h2>
      <el-button type="primary" @click="handleAdd">
        <el-icon><Plus /></el-icon>
        添加黑名单
      </el-button>
    </div>

    <el-card class="filter-card">
      <el-form :inline="true" :model="filterForm">
        <el-form-item label="类型">
          <el-select v-model="filterForm.type" placeholder="全部" clearable style="width: 140px">
            <el-option label="IP地址" :value="1" />
            <el-option label="设备" :value="2" />
            <el-option label="手机号" :value="3" />
            <el-option label="邮箱" :value="4" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filterForm.status" placeholder="全部" clearable style="width: 120px">
            <el-option label="生效中" :value="1" />
            <el-option label="已过期" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item label="关键词">
          <el-input
            v-model="filterForm.keyword"
            placeholder="搜索值"
            clearable
            style="width: 200px"
            @keyup.enter="fetchList"
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
        <el-table-column prop="type_text" label="类型" width="100">
          <template #default="{ row }">
            <el-tag :type="getTypeTagType(row.type)">{{ row.type_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="value" label="值" min-width="200" />
        <el-table-column prop="reason" label="原因" min-width="150" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'info'">
              {{ row.is_active ? '生效中' : '已过期' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="expire_time" label="过期时间" width="180">
          <template #default="{ row }">
            {{ row.expire_time || '永久' }}
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleEdit(row)">编辑</el-button>
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

    <el-dialog
      v-model="dialogVisible"
      :title="editId ? '编辑黑名单' : '添加黑名单'"
      width="500px"
    >
      <el-form :model="form" label-width="100px">
        <el-form-item label="类型">
          <el-select v-model="form.type" placeholder="请选择类型" style="width: 100%">
            <el-option label="IP地址" :value="1" />
            <el-option label="设备" :value="2" />
            <el-option label="手机号" :value="3" />
            <el-option label="邮箱" :value="4" />
          </el-select>
        </el-form-item>
        <el-form-item label="值">
          <el-input v-model="form.value" placeholder="请输入值" />
        </el-form-item>
        <el-form-item label="原因">
          <el-input
            v-model="form.reason"
            type="textarea"
            :rows="3"
            placeholder="请输入原因"
          />
        </el-form-item>
        <el-form-item label="过期时间">
          <el-date-picker
            v-model="form.expire_time"
            type="datetime"
            placeholder="选择过期时间，不选为永久"
            style="width: 100%"
            value-format="YYYY-MM-DD HH:mm:ss"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitting">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  getBlacklist,
  addBlacklist,
  updateBlacklist,
  deleteBlacklist
} from '@/api/admin/risk'

const loading = ref(false)
const submitting = ref(false)
const list = ref([])

const filterForm = reactive({
  type: '',
  status: '',
  keyword: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

const dialogVisible = ref(false)
const editId = ref(null)
const form = reactive({
  type: 1,
  value: '',
  reason: '',
  expire_time: ''
})

function getTypeTagType(type) {
  const map = {
    1: 'primary',
    2: 'warning',
    3: 'danger',
    4: 'info'
  }
  return map[type] || 'info'
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getBlacklist({
      page: pagination.page,
      pageSize: pagination.pageSize,
      type: filterForm.type,
      status: filterForm.status,
      keyword: filterForm.keyword
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
  filterForm.type = ''
  filterForm.status = ''
  filterForm.keyword = ''
  pagination.page = 1
  fetchList()
}

function handleAdd() {
  editId.value = null
  form.type = 1
  form.value = ''
  form.reason = ''
  form.expire_time = ''
  dialogVisible.value = true
}

function handleEdit(row) {
  editId.value = row.id
  form.type = row.type
  form.value = row.value
  form.reason = row.reason || ''
  form.expire_time = row.expire_time || ''
  dialogVisible.value = true
}

async function handleSubmit() {
  if (!form.type || !form.value) {
    ElMessage.warning('请填写完整信息')
    return
  }

  submitting.value = true
  try {
    const data = {
      type: form.type,
      value: form.value,
      reason: form.reason,
      expire_time: form.expire_time
    }

    const res = editId.value
      ? await updateBlacklist(editId.value, data)
      : await addBlacklist(data)

    if (res.code === 0) {
      ElMessage.success(editId.value ? '更新成功' : '添加成功')
      dialogVisible.value = false
      fetchList()
    }
  } catch (e) {
    console.error(e)
  } finally {
    submitting.value = false
  }
}

function handleDelete(row) {
  ElMessageBox.confirm(
    `确定要删除黑名单「${row.value}」吗？`,
    '提示',
    {
      type: 'warning',
      confirmButtonText: '确定',
      cancelButtonText: '取消'
    }
  ).then(async () => {
    try {
      const res = await deleteBlacklist(row.id)
      if (res.code === 0) {
        ElMessage.success('删除成功')
        fetchList()
      }
    } catch (e) {
      console.error(e)
    }
  }).catch(() => {})
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.risk-blacklist {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
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

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>
