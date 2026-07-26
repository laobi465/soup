<template>
  <div class="merchant-management">
    <div class="page-header">
      <h2>商户管理</h2>
    </div>

    <div class="search-bar">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="关键词">
          <el-input v-model="searchForm.keyword" placeholder="商户名/编号/邮箱" clearable @keyup.enter="fetchList" />
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
      <el-table-column prop="merchant_no" label="商户编号" width="140" />
      <el-table-column prop="merchant_name" label="商户名称" min-width="150" />
      <el-table-column label="账号信息" min-width="180">
        <template #default="{ row }">
          <div class="user-info">
            <div v-if="row.user">
              <div>用户名：{{ row.user.username }}</div>
              <div>邮箱：{{ row.user.email }}</div>
            </div>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="套餐" width="120">
        <template #default="{ row }">
          {{ row.package ? row.package.name : '未开通' }}
        </template>
      </el-table-column>
      <el-table-column prop="balance" label="余额" width="100">
        <template #default="{ row }">
          ¥{{ row.balance }}
        </template>
      </el-table-column>
      <el-table-column prop="status" label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'">
            {{ row.status === 1 ? '正常' : '禁用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="注册时间" width="180" />
      <el-table-column label="操作" width="280" fixed="right">
        <template #default="{ row }">
          <el-button type="primary" link @click="handleDetail(row)">详情</el-button>
          <el-button :type="row.status === 1 ? 'warning' : 'success'" link @click="handleToggleStatus(row)">
            {{ row.status === 1 ? '禁用' : '启用' }}
          </el-button>
          <el-button type="info" link @click="handleResetPassword(row)">重置密码</el-button>
          <el-button type="success" link @click="handleAdjustQuota(row)">调整额度</el-button>
          <el-button type="primary" link @click="handleChangePackage(row)">变更套餐</el-button>
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

    <el-dialog v-model="resetPwdVisible" title="重置密码" width="400px">
      <el-form :model="resetPwdForm" label-width="80px">
        <el-form-item label="新密码">
          <el-input v-model="resetPwdForm.password" type="password" placeholder="留空则随机生成" show-password />
          <div class="form-tip">密码长度不能少于6位，留空则系统随机生成</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="resetPwdVisible = false">取消</el-button>
        <el-button type="primary" @click="submitResetPassword" :loading="resetPwdLoading">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="quotaVisible" title="调整额度" width="400px">
      <el-form :model="quotaForm" :rules="quotaRules" ref="quotaFormRef" label-width="80px">
        <el-form-item label="类型" prop="type">
          <el-radio-group v-model="quotaForm.type">
            <el-radio value="app">应用数</el-radio>
            <el-radio value="card">卡密数</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="调整数量" prop="amount">
          <el-input-number v-model="quotaForm.amount" :step="1" style="width: 100%" />
          <div class="form-tip">正数增加，负数减少</div>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="quotaForm.remark" type="textarea" :rows="2" placeholder="请输入备注信息" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="quotaVisible = false">取消</el-button>
        <el-button type="primary" @click="submitAdjustQuota" :loading="quotaLoading">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="packageVisible" title="变更套餐" width="450px">
      <el-form :model="packageForm" :rules="packageRules" ref="packageFormRef" label-width="80px">
        <el-form-item label="套餐" prop="package_id">
          <el-select v-model="packageForm.package_id" placeholder="请选择套餐" style="width: 100%">
            <el-option
              v-for="pkg in packageOptions"
              :key="pkg.id"
              :label="pkg.name"
              :value="pkg.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="时长" prop="duration">
          <el-radio-group v-model="packageForm.duration">
            <el-radio value="month">月付</el-radio>
            <el-radio value="quarter">季付</el-radio>
            <el-radio value="year">年付</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="packageVisible = false">取消</el-button>
        <el-button type="primary" @click="submitChangePackage" :loading="packageLoading">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getMerchantList,
  updateMerchantStatus,
  resetMerchantPassword,
  adjustMerchantQuota,
  changeMerchantPackage
} from '@/api/admin/merchant'
import { getPackageList } from '@/api/admin/package'

const router = useRouter()
const loading = ref(false)
const tableData = ref([])

const searchForm = reactive({
  keyword: '',
  status: '',
  package_id: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

const currentMerchant = ref(null)

const resetPwdVisible = ref(false)
const resetPwdLoading = ref(false)
const resetPwdForm = reactive({
  password: ''
})

const quotaVisible = ref(false)
const quotaLoading = ref(false)
const quotaFormRef = ref(null)
const quotaForm = reactive({
  type: 'app',
  amount: 0,
  remark: ''
})

const quotaRules = {
  type: [{ required: true, message: '请选择类型', trigger: 'change' }],
  amount: [{ required: true, message: '请输入调整数量', trigger: 'blur' }]
}

const packageVisible = ref(false)
const packageLoading = ref(false)
const packageFormRef = ref(null)
const packageOptions = ref([])
const packageForm = reactive({
  package_id: 0,
  duration: 'month'
})

const packageRules = {
  package_id: [{ required: true, message: '请选择套餐', trigger: 'change' }],
  duration: [{ required: true, message: '请选择时长', trigger: 'change' }]
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getMerchantList({
      page: pagination.page,
      pageSize: pagination.pageSize,
      keyword: searchForm.keyword,
      status: searchForm.status,
      package_id: searchForm.package_id
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
  searchForm.package_id = ''
  pagination.page = 1
  fetchList()
}

function handleDetail(row) {
  router.push({ path: `/merchant/detail/${row.id}` })
}

function handleToggleStatus(row) {
  const action = row.status === 1 ? '禁用' : '启用'
  ElMessageBox.confirm(`确定要${action}商户「${row.merchant_name}」吗？`, '提示', {
    type: 'warning',
    confirmButtonText: '确定',
    cancelButtonText: '取消'
  }).then(async () => {
    const res = await updateMerchantStatus(row.id, row.status === 1 ? 0 : 1)
    if (res.code === 0) {
      ElMessage.success(`${action}成功`)
      fetchList()
    }
  }).catch(() => {})
}

function handleResetPassword(row) {
  currentMerchant.value = row
  resetPwdForm.password = ''
  resetPwdVisible.value = true
}

async function submitResetPassword() {
  if (!currentMerchant.value) return
  resetPwdLoading.value = true
  try {
    const res = await resetMerchantPassword(currentMerchant.value.id, resetPwdForm.password)
    if (res.code === 0) {
      if (res.data && res.data.password) {
        ElMessage.success(`密码重置成功，新密码：${res.data.password}`)
      } else {
        ElMessage.success('密码重置成功')
      }
      resetPwdVisible.value = false
    }
  } finally {
    resetPwdLoading.value = false
  }
}

function handleAdjustQuota(row) {
  currentMerchant.value = row
  quotaForm.type = 'app'
  quotaForm.amount = 0
  quotaForm.remark = ''
  quotaVisible.value = true
}

async function submitAdjustQuota() {
  if (!quotaFormRef.value || !currentMerchant.value) return
  await quotaFormRef.value.validate(async (valid) => {
    if (!valid) return
    quotaLoading.value = true
    try {
      const res = await adjustMerchantQuota(currentMerchant.value.id, {
        type: quotaForm.type,
        amount: quotaForm.amount,
        remark: quotaForm.remark
      })
      if (res.code === 0) {
        ElMessage.success('额度调整成功')
        quotaVisible.value = false
        fetchList()
      }
    } finally {
      quotaLoading.value = false
    }
  })
}

async function loadPackageOptions() {
  try {
    const res = await getPackageList({ page: 1, pageSize: 100, status: 1 })
    if (res.code === 0) {
      packageOptions.value = res.data.list
    }
  } catch (e) {
    console.error(e)
  }
}

function handleChangePackage(row) {
  currentMerchant.value = row
  packageForm.package_id = row.package_id || 0
  packageForm.duration = 'month'
  packageVisible.value = true
}

async function submitChangePackage() {
  if (!packageFormRef.value || !currentMerchant.value) return
  await packageFormRef.value.validate(async (valid) => {
    if (!valid) return
    packageLoading.value = true
    try {
      const res = await changeMerchantPackage(currentMerchant.value.id, {
        package_id: packageForm.package_id,
        duration: packageForm.duration
      })
      if (res.code === 0) {
        ElMessage.success('套餐变更成功')
        packageVisible.value = false
        fetchList()
      }
    } finally {
      packageLoading.value = false
    }
  })
}

onMounted(() => {
  fetchList()
  loadPackageOptions()
})
</script>

<style scoped>
.merchant-management {
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

.user-info {
  font-size: 13px;
  color: #606266;
  line-height: 1.6;
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
