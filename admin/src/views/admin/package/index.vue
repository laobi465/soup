<template>
  <div class="package-management">
    <div class="page-header">
      <h2>套餐管理</h2>
      <el-button type="primary" @click="handleAdd">
        <el-icon><Plus /></el-icon>
        新增套餐
      </el-button>
    </div>

    <div class="search-bar">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="套餐名称">
          <el-input v-model="searchForm.name" placeholder="请输入套餐名称" clearable @keyup.enter="fetchList" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 120px">
            <el-option label="启用" :value="1" />
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
      <el-table-column prop="name" label="套餐名称" min-width="120" />
      <el-table-column label="价格" min-width="240">
        <template #default="{ row }">
          <div class="price-cell">
            <span>月付：¥{{ row.price_month }}</span>
            <span>季付：¥{{ row.price_quarter }}</span>
            <span>年付：¥{{ row.price_year }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column prop="app_limit" label="应用限制" width="100">
        <template #default="{ row }">
          {{ row.app_limit === 0 ? '不限' : row.app_limit }}
        </template>
      </el-table-column>
      <el-table-column prop="card_limit" label="卡密限制" width="120">
        <template #default="{ row }">
          {{ row.card_limit === 0 ? '不限' : row.card_limit }}
        </template>
      </el-table-column>
      <el-table-column prop="api_limit_day" label="日API限制" width="120">
        <template #default="{ row }">
          {{ row.api_limit_day === 0 ? '不限' : row.api_limit_day }}
        </template>
      </el-table-column>
      <el-table-column prop="sort" label="排序" width="80" />
      <el-table-column prop="status" label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'">
            {{ row.status === 1 ? '启用' : '禁用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="创建时间" width="180" />
      <el-table-column label="操作" width="200" fixed="right">
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

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="600px" @close="resetForm">
      <el-form :model="formData" :rules="formRules" ref="formRef" label-width="100px">
        <el-form-item label="套餐名称" prop="name">
          <el-input v-model="formData.name" maxlength="50" show-word-limit />
        </el-form-item>
        <el-form-item label="月付价格" prop="price_month">
          <el-input-number v-model="formData.price_month" :min="0" :precision="2" :step="10" style="width: 100%" />
        </el-form-item>
        <el-form-item label="季付价格" prop="price_quarter">
          <el-input-number v-model="formData.price_quarter" :min="0" :precision="2" :step="10" style="width: 100%" />
        </el-form-item>
        <el-form-item label="年付价格" prop="price_year">
          <el-input-number v-model="formData.price_year" :min="0" :precision="2" :step="100" style="width: 100%" />
        </el-form-item>
        <el-form-item label="应用数量限制" prop="app_limit">
          <el-input-number v-model="formData.app_limit" :min="0" :step="1" style="width: 100%" />
          <div class="form-tip">0表示不限</div>
        </el-form-item>
        <el-form-item label="卡密数量限制" prop="card_limit">
          <el-input-number v-model="formData.card_limit" :min="0" :step="100" style="width: 100%" />
          <div class="form-tip">0表示不限</div>
        </el-form-item>
        <el-form-item label="日API调用限制" prop="api_limit_day">
          <el-input-number v-model="formData.api_limit_day" :min="0" :step="1000" style="width: 100%" />
          <div class="form-tip">0表示不限</div>
        </el-form-item>
        <el-form-item label="在线设备限制" prop="online_limit">
          <el-input-number v-model="formData.online_limit" :min="0" :step="1" style="width: 100%" />
          <div class="form-tip">0表示不限</div>
        </el-form-item>
        <el-form-item label="子账号限制" prop="sub_account_limit">
          <el-input-number v-model="formData.sub_account_limit" :min="0" :step="1" style="width: 100%" />
          <div class="form-tip">0表示不限</div>
        </el-form-item>
        <el-form-item label="排序" prop="sort">
          <el-input-number v-model="formData.sort" :step="1" style="width: 100%" />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="formData.status">
            <el-radio :value="1">启用</el-radio>
            <el-radio :value="0">禁用</el-radio>
          </el-radio-group>
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
import { getPackageList, createPackage, updatePackage, deletePackage } from '@/api/admin/package'

const loading = ref(false)
const submitting = ref(false)
const dialogVisible = ref(false)
const dialogTitle = ref('新增套餐')
const formRef = ref(null)
const isEdit = ref(false)

const searchForm = reactive({
  name: '',
  status: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

const tableData = ref([])

const defaultFormData = {
  id: 0,
  name: '',
  price_month: 0,
  price_quarter: 0,
  price_year: 0,
  app_limit: 0,
  card_limit: 0,
  api_limit_day: 0,
  online_limit: 0,
  sub_account_limit: 0,
  features: null,
  sort: 0,
  status: 1
}

const formData = reactive({ ...defaultFormData })

const formRules = {
  name: [{ required: true, message: '请输入套餐名称', trigger: 'blur' }],
  price_month: [{ required: true, message: '请输入月付价格', trigger: 'blur' }],
  price_quarter: [{ required: true, message: '请输入季付价格', trigger: 'blur' }],
  price_year: [{ required: true, message: '请输入年付价格', trigger: 'blur' }]
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getPackageList({
      page: pagination.page,
      pageSize: pagination.pageSize,
      name: searchForm.name,
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
  searchForm.name = ''
  searchForm.status = ''
  pagination.page = 1
  fetchList()
}

function handleAdd() {
  isEdit.value = false
  dialogTitle.value = '新增套餐'
  Object.assign(formData, defaultFormData)
  dialogVisible.value = true
}

function handleEdit(row) {
  isEdit.value = true
  dialogTitle.value = '编辑套餐'
  Object.assign(formData, row)
  dialogVisible.value = true
}

function resetForm() {
  formRef.value?.resetFields()
  Object.assign(formData, defaultFormData)
}

async function handleSubmit() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    submitting.value = true
    try {
      if (isEdit.value) {
        const res = await updatePackage(formData.id, formData)
        if (res.code === 0) {
          ElMessage.success('更新成功')
          dialogVisible.value = false
          fetchList()
        }
      } else {
        const res = await createPackage(formData)
        if (res.code === 0) {
          ElMessage.success('创建成功')
          dialogVisible.value = false
          fetchList()
        }
      }
    } finally {
      submitting.value = false
    }
  })
}

function handleDelete(row) {
  ElMessageBox.confirm(`确定要删除套餐「${row.name}」吗？`, '提示', {
    type: 'warning',
    confirmButtonText: '确定',
    cancelButtonText: '取消'
  }).then(async () => {
    const res = await deletePackage(row.id)
    if (res.code === 0) {
      ElMessage.success('删除成功')
      fetchList()
    }
  }).catch(() => {})
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.package-management {
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

.search-bar {
  margin-bottom: 20px;
  padding: 16px;
  background: #f5f7fa;
  border-radius: 4px;
}

.price-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
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
