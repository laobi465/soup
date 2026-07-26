<template>
  <div class="message-center">
    <div class="page-header">
      <h2>消息通知</h2>
      <el-button type="primary" @click="handleMarkAllRead" :disabled="unreadCount === 0">
        <el-icon><Check /></el-icon>
        全部已读 ({{ unreadCount }})
      </el-button>
    </div>

    <el-card class="filter-card">
      <el-radio-group v-model="filterType" @change="fetchList">
        <el-radio-button value="">全部</el-radio-button>
        <el-radio-button value="1">系统通知</el-radio-button>
        <el-radio-button value="2">套餐提醒</el-radio-button>
        <el-radio-button value="3">卡密提醒</el-radio-button>
        <el-radio-button value="4">提现通知</el-radio-button>
        <el-radio-button value="5">工单通知</el-radio-button>
        <el-radio-button value="6">异常告警</el-radio-button>
      </el-radio-group>
      <el-radio-group v-model="filterRead" style="margin-left: 20px" @change="fetchList">
        <el-radio-button value="">全部</el-radio-button>
        <el-radio-button value="0">未读</el-radio-button>
        <el-radio-button value="1">已读</el-radio-button>
      </el-radio-group>
    </el-card>

    <el-card v-loading="loading" class="list-card">
      <el-empty v-if="list.length === 0" description="暂无消息" />
      <div v-else class="message-list">
        <div
          v-for="item in list"
          :key="item.id"
          class="message-item"
          :class="{ unread: !item.is_read }"
          @click="handleMessageClick(item)"
        >
          <div class="message-icon" :class="getTypeClass(item.type)">
            <el-icon :size="20">
              <component :is="getTypeIcon(item.type)" />
            </el-icon>
          </div>
          <div class="message-content">
            <div class="message-header">
              <span class="message-title">{{ item.title }}</span>
              <span class="message-time">{{ item.created_at }}</span>
            </div>
            <div class="message-text">{{ item.content }}</div>
          </div>
          <div class="message-status">
            <el-tag v-if="!item.is_read" type="danger" size="small">未读</el-tag>
          </div>
        </div>
      </div>

      <div class="pagination" v-if="total > 0">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.pageSize"
          :total="pagination.total"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="fetchList"
          @current-change="fetchList"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import {
  Bell,
  Goods,
  Key,
  Wallet,
  Service,
  Warning,
  Check
} from '@element-plus/icons-vue'
import {
  getMessageList,
  getUnreadCount,
  markAsRead,
  markAllAsRead
} from '@/api/message'

const loading = ref(false)
const list = ref([])
const unreadCount = ref(0)
const total = ref(0)

const filterType = ref('')
const filterRead = ref('')

const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

function getTypeClass(type) {
  const map = {
    1: 'type-system',
    2: 'type-package',
    3: 'type-card',
    4: 'type-withdraw',
    5: 'type-ticket',
    6: 'type-alert'
  }
  return map[type] || 'type-system'
}

function getTypeIcon(type) {
  const map = {
    1: Bell,
    2: Goods,
    3: Key,
    4: Wallet,
    5: Service,
    6: Warning
  }
  return map[type] || Bell
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getMessageList({
      page: pagination.page,
      pageSize: pagination.pageSize,
      type: filterType.value || undefined,
      is_read: filterRead.value !== '' ? filterRead.value : undefined
    })
    if (res.code === 0) {
      list.value = res.data.list
      pagination.total = res.data.total
      unreadCount.value = res.data.unread_count || 0
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function fetchUnreadCount() {
  try {
    const res = await getUnreadCount()
    if (res.code === 0) {
      unreadCount.value = res.data.count
    }
  } catch (e) {
    console.error(e)
  }
}

async function handleMessageClick(item) {
  if (!item.is_read) {
    try {
      const res = await markAsRead(item.id)
      if (res.code === 0) {
        item.is_read = 1
        unreadCount.value = Math.max(0, unreadCount.value - 1)
      }
    } catch (e) {
      console.error(e)
    }
  }
}

async function handleMarkAllRead() {
  try {
    const res = await markAllAsRead()
    if (res.code === 0) {
      ElMessage.success('已全部标记为已读')
      list.value.forEach(item => {
        item.is_read = 1
      })
      unreadCount.value = 0
    }
  } catch (e) {
    console.error(e)
  }
}

onMounted(() => {
  fetchList()
  fetchUnreadCount()
})
</script>

<style scoped>
.message-center {
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

.message-list {
  display: flex;
  flex-direction: column;
}

.message-item {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 16px;
  border-bottom: 1px solid #f0f2f5;
  cursor: pointer;
  transition: background 0.2s;
}

.message-item:hover {
  background: #f5f7fa;
}

.message-item.unread {
  background: #ecf5ff;
}

.message-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
}

.message-icon.type-system {
  background: #409eff;
}

.message-icon.type-package {
  background: #e6a23c;
}

.message-icon.type-card {
  background: #67c23a;
}

.message-icon.type-withdraw {
  background: #f56c6c;
}

.message-icon.type-ticket {
  background: #909399;
}

.message-icon.type-alert {
  background: #f56c6c;
}

.message-content {
  flex: 1;
  min-width: 0;
}

.message-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}

.message-title {
  font-size: 15px;
  font-weight: 500;
  color: #303133;
}

.message-time {
  font-size: 12px;
  color: #909399;
}

.message-text {
  font-size: 14px;
  color: #606266;
  line-height: 1.5;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.message-status {
  flex-shrink: 0;
}

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>
