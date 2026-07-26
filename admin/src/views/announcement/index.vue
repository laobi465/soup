<template>
  <div class="announcement-list">
    <div class="page-header">
      <h2>公告中心</h2>
    </div>

    <div class="tabs">
      <el-radio-group v-model="activeType" size="default" @change="fetchList">
        <el-radio-button value="">全部</el-radio-button>
        <el-radio-button value="1">系统公告</el-radio-button>
        <el-radio-button value="2">活动公告</el-radio-button>
        <el-radio-button value="3">维护公告</el-radio-button>
      </el-radio-group>
    </div>

    <div v-loading="loading" class="list-container">
      <el-empty v-if="!loading && list.length === 0" description="暂无公告" />

      <div v-for="item in list" :key="item.id" class="announcement-item" @click="goDetail(item.id)">
        <div class="item-header">
          <el-tag :type="getTypeTagType(item.type)" size="small">{{ item.type_text }}</el-tag>
          <span class="item-title">{{ item.title }}</span>
        </div>
        <div class="item-footer">
          <span class="item-date">{{ item.created_at }}</span>
          <span class="item-more">查看详情 →</span>
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
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getPublicAnnouncements } from '@/api/admin/announcement'

const router = useRouter()
const loading = ref(false)
const activeType = ref('')
const list = ref([])
const page = ref(1)
const pageSize = ref(10)
const total = ref(0)

function getTypeTagType(type) {
  const map = { 1: '', 2: 'warning', 3: 'danger' }
  return map[type] || ''
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getPublicAnnouncements({
      page: page.value,
      page_size: pageSize.value,
      type: activeType.value
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
  router.push('/announcement/' + id)
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.announcement-list {
  padding: 20px;
  max-width: 800px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 24px;
  font-weight: 600;
}

.tabs {
  margin-bottom: 20px;
}

.list-container {
  background: #fff;
  border-radius: 8px;
  padding: 12px;
  min-height: 400px;
}

.announcement-item {
  padding: 16px;
  border-bottom: 1px solid #f0f0f0;
  cursor: pointer;
  transition: background-color 0.2s;
}

.announcement-item:hover {
  background-color: #f5f7fa;
}

.announcement-item:last-child {
  border-bottom: none;
}

.item-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
}

.item-title {
  font-size: 16px;
  font-weight: 500;
  color: #303133;
}

.item-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.item-date {
  font-size: 13px;
  color: #909399;
}

.item-more {
  font-size: 13px;
  color: #409eff;
}

.pagination {
  margin-top: 20px;
  justify-content: center;
}
</style>
