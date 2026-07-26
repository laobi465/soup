<template>
  <div class="not-found-container">
    <div class="not-found-content">
      <h1 class="error-code">404</h1>
      <p class="error-message">页面不存在或已被移除</p>
      <el-button type="primary" @click="goHome">返回首页</el-button>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

function goHome() {
  router.push('/')
}
</script>

<style scoped>
.not-found-container {
  width: 100%;
  height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f5f7fa;
}

.not-found-content {
  text-align: center;
}

.error-code {
  font-size: 120px;
  font-weight: bold;
  color: #409eff;
  margin: 0 0 20px 0;
}

.error-message {
  font-size: 18px;
  color: #606266;
  margin: 0 0 30px 0;
}
</style>
