<template>
  <div class="shop-product">
    <div class="page-header">
      <h2>商品管理</h2>
      <el-button type="primary" @click="handleAdd">
        <el-icon><Plus /></el-icon>
        新增商品
      </el-button>
    </div>

    <div class="search-bar">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="商品名称">
          <el-input v-model="searchForm.keyword" placeholder="请输入商品名称" clearable @keyup.enter="fetchList" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 120px">
            <el-option label="上架中" :value="1" />
            <el-option label="已下架" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item label="分类">
          <el-input v-model="searchForm.category" placeholder="请输入分类" clearable style="width: 140px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetchList">搜索</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <el-table :data="tableData" v-loading="loading" border stripe>
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="name" label="商品名称" min-width="150" />
      <el-table-column prop="category" label="分类" width="100" />
      <el-table-column prop="price" label="价格" width="100">
        <template #default="{ row }">
          ¥{{ row.price }}
        </template>
      </el-table-column>
      <el-table-column prop="stock" label="库存" width="100" />
      <el-table-column label="限购" width="200">
        <template #default="{ row }">
          <el-tag size="small">用户:{{ row.limit_per_user || '不限' }}</el-tag>
          <el-tag size="small" style="margin-left: 4px;">IP:{{ row.limit_per_ip || '不限' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="status_text" label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'info'">{{ row.status_text }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="创建时间" width="180" />
      <el-table-column label="操作" width="220" fixed="right">
        <template #default="{ row }">
          <el-button type="primary" link @click="handleEdit(row)">编辑</el-button>
          <el-button
            :type="row.status === 1 ? 'warning' : 'success'"
            link
            @click="handleToggleStatus(row)"
          >
            {{ row.status === 1 ? '下架' : '上架' }}
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

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑商品' : '新增商品'" width="600px">
      <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
        <el-form-item label="商品名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入商品名称" />
        </el-form-item>

        <el-form-item label="关联应用" prop="app_id">
          <el-select v-model="form.app_id" placeholder="请选择关联应用" style="width: 100%">
            <el-option
              v-for="app in appList"
              :key="app.id"
              :label="app.app_name"
              :value="app.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="商品图片">
          <el-input v-model="form.image" placeholder="请输入图片URL" />
        </el-form-item>

        <el-form-item label="商品分类">
          <el-input v-model="form.category" placeholder="请输入分类名称" />
        </el-form-item>

        <el-form-item label="商品价格" prop="price">
          <el-input-number v-model="form.price" :min="0" :precision="2" :step="1" style="width: 100%" />
        </el-form-item>

        <el-form-item label="库存数量" prop="stock">
          <el-input-number v-model="form.stock" :min="0" :step="10" style="width: 100%" />
        </el-form-item>

        <el-form-item label="商品描述">
          <el-input v-model="form.description" type="textarea" :rows="4" placeholder="请输入商品描述" />
        </el-form-item>

        <el-divider>限购设置</el-divider>

        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="每用户限购">
              <el-input-number v-model="form.limit_per_user" :min="0" :step="1" />
              <div class="form-tip">0表示不限</div>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="每IP限购">
              <el-input-number v-model="form.limit_per_ip" :min="0" :step="1" />
              <div class="form-tip">0表示不限</div>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="每设备限购">
              <el-input-number v-model="form.limit_per_device" :min="0" :step="1" />
              <div class="form-tip">0表示不限</div>
            </el-form-item>
          </el-col>
        </el-row>
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
  getShopProductList,
  createShopProduct,
  updateShopProduct,
  updateShopProductStatus,
  deleteShopProduct
} from '@/api/merchant/shop'

const loading = ref(false)
const submitting = ref(false)
const tableData = ref([])
const dialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref(null)
const appList = ref([])

const searchForm = reactive({
  keyword: '',
  status: '',
  category: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

const form = reactive({
  id: 0,
  name: '',
  app_id: 0,
  image: '',
  category: '',
  price: 0,
  stock: 0,
  description: '',
  limit_per_user: 0,
  limit_per_ip: 0,
  limit_per_device: 0
})

const rules = {
  name: [{ required: true, message: '请输入商品名称', trigger: 'blur' }],
  app_id: [{ required: true, message: '请选择关联应用', trigger: 'change' }],
  price: [{ required: true, message: '请输入商品价格', trigger: 'blur' }],
  stock: [{ required: true, message: '请输入库存数量', trigger: 'blur' }]
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getShopProductList({
      page: pagination.page,
      pageSize: pagination.pageSize,
      keyword: searchForm.keyword,
      status: searchForm.status,
      category: searchForm.category
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
  searchForm.status = ''
  searchForm.category = ''
  pagination.page = 1
  fetchList()
}

function handleAdd() {
  isEdit.value = false
  form.id = 0
  form.name = ''
  form.app_id = 0
  form.image = ''
  form.category = ''
  form.price = 0
  form.stock = 0
  form.description = ''
  form.limit_per_user = 0
  form.limit_per_ip = 0
  form.limit_per_device = 0
  dialogVisible.value = true
}

function handleEdit(row) {
  isEdit.value = true
  form.id = row.id
  form.name = row.name
  form.app_id = row.app_id
  form.image = row.image || ''
  form.category = row.category || ''
  form.price = row.price
  form.stock = row.stock
  form.description = row.description || ''
  form.limit_per_user = row.limit_per_user
  form.limit_per_ip = row.limit_per_ip
  form.limit_per_device = row.limit_per_device
  dialogVisible.value = true
}

async function handleSubmit() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    submitting.value = true
    try {
      let res
      if (isEdit.value) {
        res = await updateShopProduct(form.id, form)
      } else {
        res = await createShopProduct(form)
      }
      if (res.code === 0) {
        ElMessage.success(isEdit.value ? '更新成功' : '创建成功')
        dialogVisible.value = false
        fetchList()
      }
    } finally {
      submitting.value = false
    }
  })
}

function handleToggleStatus(row) {
  const action = row.status === 1 ? '下架' : '上架'
  ElMessageBox.confirm(`确定要${action}商品「${row.name}」吗？`, '提示', {
    type: 'warning',
    confirmButtonText: '确定',
    cancelButtonText: '取消'
  }).then(async () => {
    const res = await updateShopProductStatus(row.id, row.status === 1 ? 0 : 1)
    if (res.code === 0) {
      ElMessage.success(`${action}成功`)
      fetchList()
    }
  }).catch(() => {})
}

function handleDelete(row) {
  ElMessageBox.confirm(`确定要删除商品「${row.name}」吗？`, '提示', {
    type: 'warning',
    confirmButtonText: '确定',
    cancelButtonText: '取消'
  }).then(async () => {
    const res = await deleteShopProduct(row.id)
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
.shop-product {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
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
