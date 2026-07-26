<template>
  <div class="my-tickets">
    <div class="page-header">
      <h2>我的工单</h2>
      <el-button type="primary" @click="handleCreate">提交工单</el-button>
    </div>

    <div class="status-tabs">
      <el-radio-group v-model="activeStatus" size="default" @change="fetchList">
        <el-radio-button value="">全部</el-radio-button>
        <el-radio-button value="1">待处理</el-radio-button>
        <el-radio-button value="2">处理中</el-radio-button>
        <el-radio-button value="3">已解决</el-radio-button>
        <el-radio-button value="4">已关闭</el-radio-button>
      </el-radio-group>
    </div>

    <el-card v-loading="loading" class="list-card">
      <el-empty v-if="!loading && list.length === 0" description="暂无工单" />

      <div v-for="item in list" :key="item.id" class="ticket-item" @click="goDetail(item.id)">
        <div class="ticket-header">
          <span class="ticket-no">{{ item.ticket_no }}</span>
          <el-tag :type="getStatusTagType(item.status)" size="small">{{ item.status_text }}</el-tag>
        </div>
        <div class="ticket-title">{{ item.title }}</div>
        <div class="ticket-footer">
          <span class="ticket-priority">
            <el-tag :type="getPriorityTagType(item.priority)" size="small">{{ item.priority_text }}</el-tag>
          </span>
          <span class="ticket-date">{{ item.created_at }}</span>
        </div>
      </div>

      <el-pagination
        v-if="total > 0"
        v-model:current-page="page"
        v-model:page-size="pageSize"
        :total="total"
        layout="prev, pager, next"
        @current-change="fetchList"
        class="pagination"
      />
    </el-card>

    <el-dialog v-model="createDialog" title="提交工单" width="600px" :close-on-click-modal="false">
      <el-form :model="createForm" :rules="createRules" ref="createFormRef" label-width="80px">
        <el-form-item label="标题" prop="title">
          <el-input v-model="createForm.title" placeholder="请输入工单标题" maxlength="100" show-word-limit />
        </el-form-item>
        <el-form-item label="优先级" prop="priority">
          <el-radio-group v-model="createForm.priority">
            <el-radio :value="1">低</el-radio>
            <el-radio :value="2">中</el-radio>
            <el-radio :value="3">高</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="内容" prop="content">
          <el-input v-model="createForm.content" type="textarea" :rows="6" placeholder="请详细描述您的问题" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialog = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitting">提交</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { getMyTickets, createTicket } from '@/api/admin/ticket'

const router = useRouter()
const loading = ref(false)
const submitting = ref(false)
const createDialog = ref(false)
const createFormRef = ref(null)

const activeStatus = ref('')
const list = ref([])
const page = ref(1)
const pageSize = ref(10)
const total = ref(0)

const createForm = reactive({
  title: '',
  priority: 2,
  content: ''
})

const createRules = {
  title: [{ required: true, message: '请输入工单标题', trigger: 'blur' }],
  content: [{ required: true, message: '请输入工单内容', trigger: 'blur' }]
}

function getStatusTagType(status) {
  const map = { 1: 'warning', 2: 'primary', 3: 'success', 4: 'info' }
  return map[status] || ''
}

function getPriorityTagType(priority) {
  const map = { 1: '', 2: 'warning', 3: 'danger' }
  return map[priority] || ''
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getMyTickets({
      page: page.value,
      page_size: pageSize.value,
      status: activeStatus.value
    })
    if (res.code === 0) {
      list.value = res.data.list
      total.value = res.data.total
    }
  } finally {
    loading.value = false
  }
}

function goDetail(id) {
  router.push('/ticket/' + id)
}

function handleCreate() {
  createForm.title = ''
  createForm.priority = 2
  createForm.content = ''
  createDialog.value = true
}

async function handleSubmit() {
  if (!createFormRef.value) return
  await createFormRef.value.validate(async (valid) => {
    if (!valid) return
    submitting.value = true
    try {
      const res = await createTicket(createForm)
      if (res.code === 0) {
        ElMessage.success('工单提交成功')
        createDialog.value = false
        fetchList()
      }
    } finally {
      submitting.value = false
    }
  })
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.my-tickets {
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

.status-tabs {
  margin-bottom: 16px;
}

.list-card {
  background: #fff;
}

.ticket-item {
  padding: 16px;
  border-bottom: 1px solid #f0f0f0;
  cursor: pointer;
  transition: background-color 0.2s;
}

.ticket-item:hover {
  background-color: #f5f7fa;
}

.ticket-item:last-child {
  border-bottom: none;
}

.ticket-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.ticket-no {
  font-size: 13px;
  color: #909399;
  font-family: monospace;
}

.ticket-title {
  font-size: 16px;
  font-weight: 500;
  color: #303133;
  margin-bottom: 8px;
}

.ticket-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.ticket-date {
  font-size: 13px;
  color: #909399;
}

.pagination {
  margin-top: 16px;
  justify-content: center;
}
</style>
