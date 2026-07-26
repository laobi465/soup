<template>
  <div class="announcement-detail">
    <div class="back-bar">
      <el-button link @click="goBack">
        <el-icon><ArrowLeft /></el-icon>
        返回列表
      </el-button>
    </div>

    <div v-loading="loading" class="detail-card">
      <div v-if="detail">
        <div class="detail-header">
          <h1 class="detail-title">{{ detail.title }}</h1>
          <div class="detail-meta">
            <el-tag :type="getTypeTagType(detail.type)" size="small">{{ detail.type_text }}</el-tag>
            <span class="meta-date">{{ detail.created_at }}</span>
          </div>
        </div>
        <div class="detail-content">
          <pre>{{ detail.content }}</pre>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft } from '@element-plus/icons-vue'
import { getPublicAnnouncementDetail } from '@/api/admin/announcement'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const detail = ref(null)

function getTypeTagType(type) {
  const map = { 1: '', 2: 'warning', 3: 'danger' }
  return map[type] || ''
}

async function fetchDetail() {
  loading.value = true
  try {
    const id = route.params.id
    const res = await getPublicAnnouncementDetail(id)
    if (res.code === 0) {
      detail.value = res.data
    }
  } finally {
    loading.value = false
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
.announcement-detail {
  padding: 20px;
  max-width: 800px;
  margin: 0 auto;
}

.back-bar {
  margin-bottom: 16px;
}

.detail-card {
  background: #fff;
  border-radius: 8px;
  padding: 32px;
  min-height: 400px;
}

.detail-header {
  text-align: center;
  margin-bottom: 24px;
  padding-bottom: 20px;
  border-bottom: 1px solid #ebeef5;
}

.detail-title {
  margin: 0 0 12px 0;
  font-size: 24px;
  font-weight: 600;
  color: #303133;
}

.detail-meta {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
}

.meta-date {
  font-size: 14px;
  color: #909399;
}

.detail-content {
  font-size: 15px;
  line-height: 1.8;
  color: #606266;
}

.detail-content pre {
  white-space: pre-wrap;
  word-wrap: break-word;
  font-family: inherit;
  margin: 0;
}
</style>
