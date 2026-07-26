<template>
  <div class="merchant-agent-tree">
    <div class="page-header">
      <h2>代理团队</h2>
    </div>

    <div class="tree-container" v-loading="loading">
      <el-tree
        :data="treeData"
        :props="{ label: 'username', children: 'children' }"
        node-key="id"
        default-expand-all
      >
        <template #default="{ node, data }">
          <div class="tree-node">
            <span class="node-name">{{ data.username }}</span>
            <el-tag size="small" :type="getLevelTagType(data.level)" style="margin-left: 8px;">
              {{ data.level_text }}
            </el-tag>
            <span class="node-earnings">收益: ¥{{ data.total_earnings }}</span>
          </div>
        </template>
      </el-tree>

      <el-empty v-if="!loading && treeData.length === 0" description="暂无代理" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getAgentTree } from '@/api/merchant/agent'

const loading = ref(false)
const treeData = ref([])

function getLevelTagType(level) {
  const map = {
    1: 'success',
    2: 'warning',
    3: 'info'
  }
  return map[level] || 'info'
}

async function fetchTree() {
  loading.value = true
  try {
    const res = await getAgentTree()
    if (res.code === 0) {
      treeData.value = res.data || []
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchTree()
})
</script>

<style scoped>
.merchant-agent-tree {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
}

.tree-container {
  background: white;
  padding: 20px;
  border-radius: 4px;
  min-height: 400px;
}

.tree-node {
  display: flex;
  align-items: center;
  flex: 1;
}

.node-name {
  font-size: 14px;
  color: #303133;
}

.node-earnings {
  margin-left: auto;
  font-size: 13px;
  color: #909399;
}
</style>
