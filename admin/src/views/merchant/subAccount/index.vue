<template>
  <div class="sub-account">
    <div class="page-header">
      <h2>子账号管理</h2>
      <el-button type="primary" @click="handleAdd">
        <el-icon><Plus /></el-icon>
        创建子账号
      </el-button>
    </div>

    <el-card class="filter-card">
      <el-form :inline="true" :model="filterForm">
        <el-form-item label="关键词">
          <el-input
            v-model="filterForm.keyword"
            placeholder="用户名/姓名/邮箱"
            clearable
            style="width: 200px"
            @keyup.enter="fetchList"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filterForm.status" placeholder="全部" clearable style="width: 120px">
            <el-option label="启用" :value="1" />
            <el-option label="禁用" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item label="角色">
          <el-select v-model="filterForm.role_id" placeholder="全部" clearable style="width: 150px">
            <el-option
              v-for="role in roleList"
              :key="role.id"
              :label="role.name"
              :value="role.id"
            />
          </el-select>
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
        <el-table-column prop="username" label="用户名" width="140" />
        <el-table-column prop="real_name" label="姓名" width="120" />
        <el-table-column prop="email" label="邮箱" min-width="180" />
        <el-table-column prop="role_name" label="角色" width="120">
          <template #default="{ row }">
            <el-tag v-if="row.role_name" type="primary" effect="plain">{{ row.role_name }}</el-tag>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="app_count" label="应用数" width="100" align="center" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'">
              {{ row.status === 1 ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="操作" width="260" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleEdit(row)">编辑</el-button>
            <el-button
              :type="row.status === 1 ? 'warning' : 'success'"
              link
              @click="handleToggleStatus(row)"
            >
              {{ row.status === 1 ? '禁用' : '启用' }}
            </el-button>
            <el-button type="info" link @click="handleResetPassword(row)">重置密码</el-button>
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
      :title="editId ? '编辑子账号' : '创建子账号'"
      width="600px"
    >
      <el-form :model="form" :rules="formRules" ref="formRef" label-width="100px">
        <el-form-item label="用户名" prop="username">
          <el-input v-model="form.username" :disabled="!!editId" placeholder="请输入用户名" />
        </el-form-item>
        <el-form-item v-if="!editId" label="密码" prop="password">
          <el-input v-model="form.password" type="password" placeholder="请输入密码" show-password />
        </el-form-item>
        <el-form-item label="姓名" prop="real_name">
          <el-input v-model="form.real_name" placeholder="请输入真实姓名" />
        </el-form-item>
        <el-form-item label="邮箱" prop="email">
          <el-input v-model="form.email" placeholder="请输入邮箱" />
        </el-form-item>
        <el-form-item label="角色">
          <el-select v-model="form.role_id" placeholder="请选择角色" style="width: 100%" clearable>
            <el-option
              v-for="role in roleList"
              :key="role.id"
              :label="role.name"
              :value="role.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="数据权限">
          <el-select
            v-model="form.app_ids"
            multiple
            placeholder="选择可访问的应用（不选为全部）"
            style="width: 100%"
          >
            <el-option
              v-for="app in appList"
              :key="app.id"
              :label="app.name"
              :value="app.id"
            />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitting">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="passwordDialogVisible" title="重置密码" width="400px">
      <el-form :model="passwordForm" :rules="passwordRules" ref="passwordFormRef" label-width="80px">
        <el-form-item label="新密码" prop="password">
          <el-input v-model="passwordForm.password" type="password" placeholder="请输入新密码" show-password />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="passwordDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleResetPasswordSubmit" :loading="resetting">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  getSubAccountList,
  createSubAccount,
  updateSubAccount,
  updateSubAccountStatus,
  resetSubAccountPassword,
  deleteSubAccount,
  getSubRoleList
} from '@/api/merchant/subAccount'
import { getAppList } from '@/api/merchant/app'

const loading = ref(false)
const submitting = ref(false)
const resetting = ref(false)
const list = ref([])
const roleList = ref([])
const appList = ref([])

const filterForm = reactive({
  keyword: '',
  status: '',
  role_id: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

const dialogVisible = ref(false)
const editId = ref(null)
const formRef = ref(null)
const form = reactive({
  username: '',
  password: '',
  real_name: '',
  email: '',
  role_id: null,
  app_ids: []
})

const formRules = {
  username: [
    { required: true, message: '请输入用户名', trigger: 'blur' },
    { min: 4, max: 20, message: '长度在4-20个字符', trigger: 'blur' }
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 6, max: 32, message: '长度在6-32个字符', trigger: 'blur' }
  ],
  email: [
    { required: true, message: '请输入邮箱', trigger: 'blur' },
    { type: 'email', message: '邮箱格式不正确', trigger: 'blur' }
  ]
}

const passwordDialogVisible = ref(false)
const passwordFormRef = ref(null)
const passwordForm = reactive({
  password: ''
})
const currentResetId = ref(null)

const passwordRules = {
  password: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 6, max: 32, message: '长度在6-32个字符', trigger: 'blur' }
  ]
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getSubAccountList({
      page: pagination.page,
      pageSize: pagination.pageSize,
      keyword: filterForm.keyword,
      status: filterForm.status,
      role_id: filterForm.role_id
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

async function fetchRoles() {
  try {
    const res = await getSubRoleList()
    if (res.code === 0) {
      roleList.value = res.data || []
    }
  } catch (e) {
    console.error(e)
  }
}

async function fetchApps() {
  try {
    const res = await getAppList({ page: 1, pageSize: 100 })
    if (res.code === 0) {
      appList.value = res.data.list || []
    }
  } catch (e) {
    console.error(e)
  }
}

function resetFilter() {
  filterForm.keyword = ''
  filterForm.status = ''
  filterForm.role_id = ''
  pagination.page = 1
  fetchList()
}

function handleAdd() {
  editId.value = null
  form.username = ''
  form.password = ''
  form.real_name = ''
  form.email = ''
  form.role_id = null
  form.app_ids = []
  dialogVisible.value = true
}

function handleEdit(row) {
  editId.value = row.id
  form.username = row.username
  form.password = ''
  form.real_name = row.real_name || ''
  form.email = row.email
  form.role_id = row.sub_role_id || null
  form.app_ids = row.app_ids || []
  dialogVisible.value = true
}

async function handleSubmit() {
  if (!formRef.value) return

  try {
    await formRef.value.validate()
  } catch {
    return
  }

  submitting.value = true
  try {
    const data = {
      username: form.username,
      real_name: form.real_name,
      email: form.email,
      role_id: form.role_id,
      app_ids: form.app_ids
    }

    if (!editId.value) {
      data.password = form.password
    }

    const res = editId.value
      ? await updateSubAccount(editId.value, data)
      : await createSubAccount(data)

    if (res.code === 0) {
      ElMessage.success(editId.value ? '更新成功' : '创建成功')
      dialogVisible.value = false
      fetchList()
    }
  } catch (e) {
    console.error(e)
  } finally {
    submitting.value = false
  }
}

function handleToggleStatus(row) {
  const action = row.status === 1 ? '禁用' : '启用'
  ElMessageBox.confirm(
    `确定要${action}子账号「${row.username}」吗？`,
    '提示',
    {
      type: 'warning',
      confirmButtonText: '确定',
      cancelButtonText: '取消'
    }
  ).then(async () => {
    try {
      const res = await updateSubAccountStatus(row.id, row.status === 1 ? 0 : 1)
      if (res.code === 0) {
        ElMessage.success(`${action}成功`)
        fetchList()
      }
    } catch (e) {
      console.error(e)
    }
  }).catch(() => {})
}

function handleResetPassword(row) {
  currentResetId.value = row.id
  passwordForm.password = ''
  passwordDialogVisible.value = true
}

async function handleResetPasswordSubmit() {
  if (!passwordFormRef.value) return

  try {
    await passwordFormRef.value.validate()
  } catch {
    return
  }

  resetting.value = true
  try {
    const res = await resetSubAccountPassword(currentResetId.value, passwordForm.password)
    if (res.code === 0) {
      ElMessage.success('重置密码成功')
      passwordDialogVisible.value = false
    }
  } catch (e) {
    console.error(e)
  } finally {
    resetting.value = false
  }
}

function handleDelete(row) {
  ElMessageBox.confirm(
    `确定要删除子账号「${row.username}」吗？删除后将无法恢复。`,
    '警告',
    {
      type: 'warning',
      confirmButtonText: '确定',
      cancelButtonText: '取消'
    }
  ).then(async () => {
    try {
      const res = await deleteSubAccount(row.id)
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
  fetchRoles()
  fetchApps()
})
</script>

<style scoped>
.sub-account {
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
