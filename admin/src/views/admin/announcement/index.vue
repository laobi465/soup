<template>
  <div class="announcement-admin">
    <div class="page-header">
      <h2>公告管理</h2>
      <el-button type="primary" @click="handleCreate">新建公告</el-button>
    </div>

    <el-card class="search-card">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="类型">
          <el-select v-model="searchForm.type" placeholder="全部" style="width: 140px" @change="fetchList">
            <el-option label="全部" value="" />
            <el-option label="系统公告" :value="1" />
            <el-option label="活动公告" :value="2" />
            <el-option label="维护公告" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" style="width: 140px" @change="fetchList">
            <el-option label="全部" value="" />
            <el-option label="已发布" :value="1" />
            <el-option label="已下架" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item label="关键词">
          <el-input v-model="searchForm.keyword" placeholder="搜索标题" clearable style="width: 200px" @keyup.enter="fetchList" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetchList">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card">
      <el-table :data="tableData" v-loading="loading" border stripe>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="title" label="标题" min-width="200" />
        <el-table-column prop="type_text" label="类型" width="120">
          <template #default="{ row }">
            <el-tag :type="getTypeTagType(row.type)">{{ row.type_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status_text" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'info'">{{ row.status_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="effective_time" label="生效时间" width="180">
          <template #default="{ row }">
            {{ row.effective_time || '立即生效' }}
          </template>
        </el-table-column>
        <el-table-column prop="expire_time" label="过期时间" width="180">
          <template #default="{ row }">
            {{ row.expire_time || '永不过期' }}
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="操作" width="240" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button link :type="row.status === 1 ? 'warning' : 'success'" @click="handleToggleStatus(row)">
              {{ row.status === 1 ? '下架' : '发布' }}
            </el-button>
            <el-button link type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.pageSize"
        :page-sizes="[10, 15, 20, 50]"
        :total="pagination.total"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="fetchList"
        @current-change="fetchList"
        class="pagination"
      />
    </el-card>

    <el-dialog v-model="dialogVisible" :title="editMode ? '编辑公告' : '新建公告'" width="700px" :close-on-click-modal="false">
      <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
        <el-form-item label="标题" prop="title">
          <el-input v-model="form.title" placeholder="请输入公告标题" maxlength="100" show-word-limit />
        </el-form-item>
        <el-form-item label="类型" prop="type">
          <el-radio-group v-model="form.type">
            <el-radio :value="1">系统公告</el-radio>
            <el-radio :value="2">活动公告</el-radio>
            <el-radio :value="3">维护公告</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="form.status">
            <el-radio :value="1">已发布</el-radio>
            <el-radio :value="0">草稿</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="生效时间">
          <el-date-picker
            v-model="form.effective_time"
            type="datetime"
            placeholder="留空则立即生效"
            value-format="YYYY-MM-DD HH:mm:ss"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="过期时间">
          <el-date-picker
            v-model="form.expire_time"
            type="datetime"
            placeholder="留空则永不过期"
            value-format="YYYY-MM-DD HH:mm:ss"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="内容" prop="content">
          <el-input v-model="form.content" type="textarea" :rows="8" placeholder="请输入公告内容" />
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
import {
  getAnnouncementList,
  getAnnouncementDetail,
  createAnnouncement,
  updateAnnouncement,
  deleteAnnouncement,
  updateAnnouncementStatus
} from '@/api/admin/announcement'

const loading = ref(false)
const submitting = ref(false)
const dialogVisible = ref(false)
const editMode = ref(false)
const formRef = ref(null)

const searchForm = reactive({
  type: '',
  status: '',
  keyword: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 15,
  total: 0
})

const tableData = ref([])

const form = reactive({
  id: 0,
  title: '',
  type: 1,
  status: 1,
  content: '',
  effective_time: '',
  expire_time: ''
})

const rules = {
  title: [{ required: true, message: '请输入公告标题', trigger: 'blur' }],
  type: [{ required: true, message: '请选择公告类型', trigger: 'change' }],
  content: [{ required: true, message: '请输入公告内容', trigger: 'blur' }]
}

function getTypeTagType(type) {
  const map = { 1: '', 2: 'warning', 3: 'danger' }
  return map[type] || ''
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getAnnouncementList({
      page: pagination.page,
      page_size: pagination.pageSize,
      type: searchForm.type,
      status: searchForm.status,
      keyword: searchForm.keyword
    })
    if (res.code === 0) {
      tableData.value = res.data.list
      pagination.total = res.data.total
    }
  } finally {
    loading.value = false
  }
}

function handleReset() {
  searchForm.type = ''
  searchForm.status = ''
  searchForm.keyword = ''
  pagination.page = 1
  fetchList()
}

function handleCreate() {
  editMode.value = false
  form.id = 0
  form.title = ''
  form.type = 1
  form.status = 1
  form.content = ''
  form.effective_time = ''
  form.expire_time = ''
  dialogVisible.value = true
}

async function handleEdit(row) {
  editMode.value = true
  const res = await getAnnouncementDetail(row.id)
  if (res.code === 0) {
    Object.assign(form, res.data)
    dialogVisible.value = true
  }
}

async function handleSubmit() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    submitting.value = true
    try {
      if (editMode.value) {
        const res = await updateAnnouncement(form.id, form)
        if (res.code === 0) {
          ElMessage.success('更新成功')
          dialogVisible.value = false
          fetchList()
        }
      } else {
        const res = await createAnnouncement(form)
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

async function handleToggleStatus(row) {
  const newStatus = row.status === 1 ? 0 : 1
  const action = newStatus === 1 ? '发布' : '下架'
  try {
    await ElMessageBox.confirm(`确定要${action}该公告吗？`, '提示', { type: 'warning' })
    const res = await updateAnnouncementStatus(row.id, newStatus)
    if (res.code === 0) {
      ElMessage.success(`${action}成功`)
      fetchList()
    }
  } catch (e) {
    // cancelled
  }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm('确定要删除该公告吗？此操作不可恢复', '删除确认', { type: 'danger' })
    const res = await deleteAnnouncement(row.id)
    if (res.code === 0) {
      ElMessage.success('删除成功')
      fetchList()
    }
  } catch (e) {
    // cancelled
  }
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.announcement-admin {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
}

.search-card {
  margin-bottom: 16px;
}

.table-card {
  background: #fff;
}

.pagination {
  margin-top: 16px;
  justify-content: flex-end;
}
</style>
