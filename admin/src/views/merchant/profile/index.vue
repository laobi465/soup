<template>
  <div class="merchant-profile">
    <div class="page-header">
      <h2>个人中心</h2>
    </div>

    <div v-loading="loading" class="profile-content">
      <el-row :gutter="20">
        <el-col :span="16">
          <el-card class="info-card">
            <template #header>
              <div class="card-header">
                <span>基本信息</span>
                <el-button type="primary" link @click="handleEdit">编辑</el-button>
              </div>
            </template>
            <el-descriptions :column="2" border v-if="!isEdit">
              <el-descriptions-item label="商户编号">{{ profile.merchant?.merchant_no }}</el-descriptions-item>
              <el-descriptions-item label="商户名称">{{ profile.merchant?.merchant_name }}</el-descriptions-item>
              <el-descriptions-item label="用户名">{{ profile.user?.username }}</el-descriptions-item>
              <el-descriptions-item label="邮箱">{{ profile.user?.email }}</el-descriptions-item>
              <el-descriptions-item label="手机号">{{ profile.user?.phone || '-' }}</el-descriptions-item>
              <el-descriptions-item label="注册时间">{{ profile.merchant?.created_at }}</el-descriptions-item>
            </el-descriptions>
            <el-form v-else :model="editForm" :rules="editRules" ref="editFormRef" label-width="100px">
              <el-form-item label="商户名称" prop="merchant_name">
                <el-input v-model="editForm.merchant_name" maxlength="100" />
              </el-form-item>
              <el-form-item label="手机号" prop="phone">
                <el-input v-model="editForm.phone" maxlength="20" />
              </el-form-item>
              <el-form-item label="头像" prop="avatar">
                <el-input v-model="editForm.avatar" placeholder="头像URL" />
              </el-form-item>
              <el-form-item>
                <el-button type="primary" @click="submitEdit" :loading="submitting">保存</el-button>
                <el-button @click="cancelEdit">取消</el-button>
              </el-form-item>
            </el-form>
          </el-card>

          <el-card class="info-card">
            <template #header>
              <div class="card-header">
                <span>修改密码</span>
              </div>
            </template>
            <el-form :model="passwordForm" :rules="passwordRules" ref="passwordFormRef" label-width="100px">
              <el-form-item label="原密码" prop="old_password">
                <el-input v-model="passwordForm.old_password" type="password" show-password />
              </el-form-item>
              <el-form-item label="新密码" prop="new_password">
                <el-input v-model="passwordForm.new_password" type="password" show-password />
                <div class="form-tip">密码长度不能少于6位</div>
              </el-form-item>
              <el-form-item label="确认密码" prop="confirm_password">
                <el-input v-model="passwordForm.confirm_password" type="password" show-password />
              </el-form-item>
              <el-form-item>
                <el-button type="primary" @click="submitChangePassword" :loading="pwdSubmitting">修改密码</el-button>
              </el-form-item>
            </el-form>
          </el-card>
        </el-col>

        <el-col :span="8">
          <el-card class="info-card">
            <template #header>
              <div class="card-header">
                <span>当前套餐</span>
              </div>
            </template>
            <div class="package-info">
              <div class="package-name">{{ profile.package?.name || '未开通' }}</div>
              <div class="package-expire">
                <span v-if="profile.package">到期时间：{{ profile.merchant?.package_expire || '永久' }}</span>
                <el-tag v-if="profile.merchant?.is_package_expired" type="danger" size="small" style="margin-left: 8px">
                  已过期
                </el-tag>
              </div>
              <div class="quota-list">
                <div class="quota-item">
                  <span class="label">应用配额</span>
                  <span class="value">
                    {{ profile.merchant?.remaining_apps >= 0 ? profile.merchant?.remaining_apps : '不限' }}
                    <span v-if="profile.merchant?.remaining_apps >= 0" class="total">/ {{ profile.merchant?.app_quota }}</span>
                  </span>
                </div>
                <div class="quota-item">
                  <span class="label">卡密配额</span>
                  <span class="value">
                    {{ profile.merchant?.remaining_cards >= 0 ? profile.merchant?.remaining_cards : '不限' }}
                    <span v-if="profile.merchant?.remaining_cards >= 0" class="total">/ {{ profile.merchant?.card_quota }}</span>
                  </span>
                </div>
                <div class="quota-item">
                  <span class="label">账户余额</span>
                  <span class="value balance">¥{{ profile.merchant?.balance || '0.00' }}</span>
                </div>
              </div>
            </div>
          </el-card>
        </el-col>
      </el-row>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getProfile, updateProfile, changePassword } from '@/api/merchant/profile'

const loading = ref(false)
const isEdit = ref(false)
const submitting = ref(false)
const pwdSubmitting = ref(false)
const editFormRef = ref(null)
const passwordFormRef = ref(null)

const profile = reactive({
  merchant: null,
  user: null,
  package: null
})

const editForm = reactive({
  merchant_name: '',
  phone: '',
  avatar: ''
})

const editRules = {
  merchant_name: [{ required: true, message: '请输入商户名称', trigger: 'blur' }]
}

const passwordForm = reactive({
  old_password: '',
  new_password: '',
  confirm_password: ''
})

const validateConfirmPassword = (rule, value, callback) => {
  if (value !== passwordForm.new_password) {
    callback(new Error('两次输入的密码不一致'))
  } else {
    callback()
  }
}

const passwordRules = {
  old_password: [{ required: true, message: '请输入原密码', trigger: 'blur' }],
  new_password: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 6, message: '密码长度不能少于6位', trigger: 'blur' }
  ],
  confirm_password: [
    { required: true, message: '请确认新密码', trigger: 'blur' },
    { validator: validateConfirmPassword, trigger: 'blur' }
  ]
}

async function fetchProfile() {
  loading.value = true
  try {
    const res = await getProfile()
    if (res.code === 0) {
      profile.merchant = res.data.merchant
      profile.user = res.data.user
      profile.package = res.data.package
      editForm.merchant_name = res.data.merchant?.merchant_name || ''
      editForm.phone = res.data.user?.phone || ''
      editForm.avatar = res.data.user?.avatar || ''
    }
  } finally {
    loading.value = false
  }
}

function handleEdit() {
  isEdit.value = true
}

function cancelEdit() {
  isEdit.value = false
  editForm.merchant_name = profile.merchant?.merchant_name || ''
  editForm.phone = profile.user?.phone || ''
  editForm.avatar = profile.user?.avatar || ''
}

async function submitEdit() {
  if (!editFormRef.value) return
  await editFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitting.value = true
    try {
      const res = await updateProfile(editForm)
      if (res.code === 0) {
        ElMessage.success('更新成功')
        isEdit.value = false
        fetchProfile()
      }
    } finally {
      submitting.value = false
    }
  })
}

async function submitChangePassword() {
  if (!passwordFormRef.value) return
  await passwordFormRef.value.validate(async (valid) => {
    if (!valid) return
    pwdSubmitting.value = true
    try {
      const res = await changePassword(passwordForm)
      if (res.code === 0) {
        ElMessage.success('密码修改成功')
        passwordForm.old_password = ''
        passwordForm.new_password = ''
        passwordForm.confirm_password = ''
        passwordFormRef.value?.resetFields()
      }
    } finally {
      pwdSubmitting.value = false
    }
  })
}

onMounted(() => {
  fetchProfile()
})
</script>

<style scoped>
.merchant-profile {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
}

.profile-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: 600;
}

.info-card {
  margin-bottom: 16px;
}

.form-tip {
  color: #909399;
  font-size: 12px;
  margin-top: 4px;
}

.package-info {
  text-align: center;
}

.package-name {
  font-size: 20px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 8px;
}

.package-expire {
  color: #909399;
  font-size: 13px;
  margin-bottom: 20px;
}

.quota-list {
  text-align: left;
}

.quota-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #ebeef5;
}

.quota-item:last-child {
  border-bottom: none;
}

.quota-item .label {
  color: #606266;
  font-size: 14px;
}

.quota-item .value {
  font-weight: 600;
  color: #303133;
}

.quota-item .value .total {
  color: #909399;
  font-weight: normal;
  font-size: 13px;
}

.quota-item .value.balance {
  color: #e6a23c;
  font-size: 18px;
}
</style>
