<template>
  <div class="ticket-detail">
    <div class="back-bar">
      <el-button link @click="goBack">
        <el-icon><ArrowLeft /></el-icon>
        返回列表
      </el-button>
    </div>

    <div v-loading="loading" class="detail-container">
      <div v-if="detail" class="detail-card">
        <div class="ticket-header">
          <div class="header-left">
            <h2 class="ticket-title">{{ detail.title }}</h2>
            <div class="ticket-meta">
              <span class="ticket-no">{{ detail.ticket_no }}</span>
              <el-tag :type="getStatusTagType(detail.status)" size="small">{{ detail.status_text }}</el-tag>
              <el-tag :type="getPriorityTagType(detail.priority)" size="small">{{ detail.priority_text }}优先级</el-tag>
            </div>
          </div>
          <div class="header-right">
            <el-button v-if="detail.status !== 4" type="danger" plain @click="handleClose">关闭工单</el-button>
          </div>
        </div>

        <div class="ticket-content">
          <pre>{{ detail.content }}</pre>
          <div class="ticket-time">创建于 {{ detail.created_at }}</div>
        </div>
      </div>

      <div v-if="detail" class="replies-card">
        <div class="replies-title">回复记录</div>

        <div class="reply-list">
          <div v-for="reply in detail.replies" :key="reply.id" :class="['reply-item', reply.user_type === 2 ? 'admin-reply' : 'user-reply']">
            <div class="reply-avatar">
              <el-avatar :size="40">
                {{ reply.user_type === 2 ? '管' : '我' }}
              </el-avatar>
            </div>
            <div class="reply-content">
              <div class="reply-header">
                <span class="reply-name">{{ reply.user_type === 2 ? '客服' : '我' }}</span>
                <span class="reply-time">{{ reply.created_at }}</span>
              </div>
              <div class="reply-body">
                <pre>{{ reply.content }}</pre>
              </div>
            </div>
          </div>
        </div>

        <div v-if="detail.status !== 4" class="reply-form">
          <el-input
            v-model="replyContent"
            type="textarea"
            :rows="4"
            placeholder="输入您的回复..."
          />
          <div class="reply-actions">
            <el-button type="primary" @click="handleReply" :loading="replying">发送回复</el-button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getMyTicketDetail, replyTicket, closeTicket } from '@/api/admin/ticket'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const replying = ref(false)
const detail = ref(null)
const replyContent = ref('')

function getStatusTagType(status) {
  const map = { 1: 'warning', 2: 'primary', 3: 'success', 4: 'info' }
  return map[status] || ''
}

function getPriorityTagType(priority) {
  const map = { 1: '', 2: 'warning', 3: 'danger' }
  return map[priority] || ''
}

async function fetchDetail() {
  loading.value = true
  try {
    const id = route.params.id
    const res = await getMyTicketDetail(id)
    if (res.code === 0) {
      detail.value = res.data
    }
  } finally {
    loading.value = false
  }
}

async function handleReply() {
  if (!replyContent.value.trim()) {
    ElMessage.warning('请输入回复内容')
    return
  }
  replying.value = true
  try {
    const res = await replyTicket(detail.value.id, replyContent.value)
    if (res.code === 0) {
      ElMessage.success('回复成功')
      replyContent.value = ''
      fetchDetail()
    }
  } finally {
    replying.value = false
  }
}

async function handleClose() {
  try {
    await ElMessageBox.confirm('确定要关闭该工单吗？关闭后将无法继续回复', '确认关闭', { type: 'warning' })
    const res = await closeTicket(detail.value.id)
    if (res.code === 0) {
      ElMessage.success('工单已关闭')
      fetchDetail()
    }
  } catch (e) {
    // cancelled
  }
}

function goBack() {
  router.back()
}

onMounted(() => {
  fetchDetail()
})
</script>

<style scoped>
.ticket-detail {
  padding: 20px;
  max-width: 900px;
  margin: 0 auto;
}

.back-bar {
  margin-bottom: 16px;
}

.detail-card,
.replies-card {
  background: #fff;
  border-radius: 8px;
  padding: 24px;
  margin-bottom: 16px;
}

.ticket-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid #ebeef5;
}

.ticket-title {
  margin: 0 0 8px 0;
  font-size: 20px;
  font-weight: 600;
}

.ticket-meta {
  display: flex;
  gap: 10px;
  align-items: center;
}

.ticket-no {
  font-size: 13px;
  color: #909399;
  font-family: monospace;
}

.ticket-content pre {
  white-space: pre-wrap;
  word-wrap: break-word;
  font-family: inherit;
  margin: 0;
  font-size: 14px;
  line-height: 1.8;
  color: #606266;
}

.ticket-time {
  margin-top: 12px;
  font-size: 12px;
  color: #909399;
}

.replies-title {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid #ebeef5;
}

.reply-list {
  margin-bottom: 20px;
}

.reply-item {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}

.admin-reply {
  flex-direction: row;
}

.user-reply {
  flex-direction: row-reverse;
}

.reply-avatar {
  flex-shrink: 0;
}

.reply-content {
  max-width: 70%;
}

.user-reply .reply-content {
  text-align: right;
}

.reply-header {
  margin-bottom: 6px;
  display: flex;
  gap: 10px;
  align-items: center;
}

.user-reply .reply-header {
  justify-content: flex-end;
}

.reply-name {
  font-weight: 500;
  font-size: 14px;
}

.admin-reply .reply-name {
  color: #409eff;
}

.reply-time {
  font-size: 12px;
  color: #909399;
}

.reply-body {
  background: #f5f7fa;
  border-radius: 8px;
  padding: 12px 16px;
}

.user-reply .reply-body {
  background: #ecf5ff;
}

.reply-body pre {
  white-space: pre-wrap;
  word-wrap: break-word;
  font-family: inherit;
  margin: 0;
  font-size: 14px;
  line-height: 1.6;
  color: #606266;
}

.reply-form {
  border-top: 1px solid #ebeef5;
  padding-top: 20px;
}

.reply-actions {
  margin-top: 12px;
  display: flex;
  justify-content: flex-end;
}
</style>
