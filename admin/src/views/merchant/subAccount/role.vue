<template>
  <div class="sub-role">
    <div class="page-header">
      <h2>角色管理</h2>
      <el-button type="primary" @click="handleAdd">
        <el-icon><Plus /></el-icon>
        创建角色
      </el-button>
    </div>

    <el-card v-loading="loading" class="list-card">
      <el-row :gutter="20">
        <el-col :span="8" v-for="role in list" :key="role.id">
          <el-card class="role-card" shadow="hover">
            <div class="role-header">
              <div class="role-icon">
                <el-icon :size="28"><UserFilled /></el-icon>
              </div>
              <div class="role-info">
                <div class="role-name">{{ role.name }}</div>
                <div class="role-desc">{{ role.description || '暂无描述' }}</div>
              </div>
            </div>
            <div class="role-permissions">
              <div class="permissions-title">权限配置</div>
              <div class="permissions-list">
                <el-tag
                  v-for="(perm, index) in (role.permissions || []).slice(0, 5)"
                  :key="index"
                  size="small"
                  type="info"
                  effect="plain"
                  class="perm-tag"
                >
                  {{ perm }}
                </el-tag>
                <el-tag
                  v-if="(role.permissions || []).length > 5"
                  size="small"
                  type="info"
                  effect="plain"
                >
                  +{{ (role.permissions || []).length - 5 }}
                </el-tag>
                <span v-if="!role.permissions || role.permissions.length === 0" class="no-perm">
                  暂无权限
                </span>
              </div>
            </div>
            <div class="role-actions">
              <el-button type="primary" link @click="handleEdit(role)">编辑</el-button>
              <el-button type="danger" link @click="handleDelete(role)">删除</el-button>
            </div>
          </el-card>
        </el-col>
      </el-row>
    </el-card>

    <el-dialog
      v-model="dialogVisible"
      :title="editId ? '编辑角色' : '创建角色'"
      width="600px"
    >
      <el-form :model="form" :rules="formRules" ref="formRef" label-width="100px">
        <el-form-item label="角色名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入角色名称" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input
            v-model="form.description"
            type="textarea"
            :rows="2"
            placeholder="请输入角色描述"
          />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort" :min="0" :max="999" />
        </el-form-item>
        <el-form-item label="权限配置">
          <div class="permissions-select">
            <el-checkbox-group v-model="form.permissions">
              <el-row :gutter="16">
                <el-col :span="12" v-for="perm in permissionOptions" :key="perm.value">
                  <el-checkbox :value="perm.value" :label="perm.value">
                    {{ perm.label }}
                  </el-checkbox>
                </el-col>
              </el-row>
            </el-checkbox-group>
          </div>
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
import { Plus, UserFilled } from '@element-plus/icons-vue'
import {
  getSubRoleList,
  createSubRole,
  updateSubRole,
  deleteSubRole
} from '@/api/merchant/subAccount'

const loading = ref(false)
const submitting = ref(false)
const list = ref([])

const permissionOptions = [
  { value: 'app:view', label: '应用查看' },
  { value: 'app:create', label: '应用创建' },
  { value: 'app:edit', label: '应用编辑' },
  { value: 'app:delete', label: '应用删除' },
  { value: 'card:view', label: '卡密查看' },
  { value: 'card:create', label: '卡密生成' },
  { value: 'card:edit', label: '卡密编辑' },
  { value: 'card:export', label: '卡密导出' },
  { value: 'order:view', label: '订单查看' },
  { value: 'order:refund', label: '订单退款' },
  { value: 'agent:view', label: '代理查看' },
  { value: 'agent:edit', label: '代理编辑' },
  { value: 'wallet:view', label: '钱包查看' },
  { value: 'wallet:recharge', label: '余额充值' },
  { value: 'profile:edit', label: '资料编辑' },
  { value: 'sub_account:manage', label: '子账号管理' }
]

const dialogVisible = ref(false)
const editId = ref(null)
const formRef = ref(null)
const form = reactive({
  name: '',
  description: '',
  sort: 0,
  permissions: []
})

const formRules = {
  name: [
    { required: true, message: '请输入角色名称', trigger: 'blur' },
    { max: 50, message: '长度不能超过50个字符', trigger: 'blur' }
  ]
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getSubRoleList()
    if (res.code === 0) {
      list.value = res.data || []
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function handleAdd() {
  editId.value = null
  form.name = ''
  form.description = ''
  form.sort = 0
  form.permissions = []
  dialogVisible.value = true
}

function handleEdit(role) {
  editId.value = role.id
  form.name = role.name
  form.description = role.description || ''
  form.sort = role.sort || 0
  form.permissions = role.permissions || []
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
      name: form.name,
      description: form.description,
      sort: form.sort,
      permissions: form.permissions
    }

    const res = editId.value
      ? await updateSubRole(editId.value, data)
      : await createSubRole(data)

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

function handleDelete(role) {
  ElMessageBox.confirm(
    `确定要删除角色「${role.name}」吗？`,
    '提示',
    {
      type: 'warning',
      confirmButtonText: '确定',
      cancelButtonText: '取消'
    }
  ).then(async () => {
    try {
      const res = await deleteSubRole(role.id)
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
.sub-role {
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

.list-card {
  margin-bottom: 20px;
}

.role-card {
  margin-bottom: 20px;
  height: 100%;
}

.role-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.role-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: #ecf5ff;
  color: #409eff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.role-info {
  flex: 1;
}

.role-name {
  font-size: 16px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 4px;
}

.role-desc {
  font-size: 12px;
  color: #909399;
}

.role-permissions {
  margin-bottom: 16px;
}

.permissions-title {
  font-size: 13px;
  color: #606266;
  margin-bottom: 8px;
}

.permissions-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  min-height: 24px;
}

.perm-tag {
  margin: 0;
}

.no-perm {
  font-size: 12px;
  color: #c0c4cc;
}

.role-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid #ebeef5;
}

.permissions-select {
  max-height: 300px;
  overflow-y: auto;
}
</style>
