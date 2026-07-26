<template>
  <div class="error-page">
    <div class="error-content">
      <div class="error-code">403</div>
      <div class="error-message">抱歉，您没有权限访问该页面</div>
      <div class="error-desc">请联系管理员获取相应权限</div>
      <div class="error-actions">
        <el-button type="primary" @click="goBack">返回上一页</el-button>
        <el-button @click="goHome">返回首页</el-button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

function goBack() {
  router.go(-1)
}

function goHome() {
  router.push('/')
}
</script>

<style scoped lang="scss">
@import '@/styles/variables.scss';

.error-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #f5f7fa 0%, #e4e7ed 100%);
  padding: $spacing-xl;
}

.error-content {
  text-align: center;
  max-width: 500px;
}

.error-code {
  font-size: 120px;
  font-weight: bold;
  color: $warning-color;
  line-height: 1;
  margin-bottom: $spacing-lg;
}

.error-message {
  font-size: $font-size-xxl;
  color: $text-primary;
  margin-bottom: $spacing-sm;
  font-weight: $font-weight-medium;
}

.error-desc {
  font-size: $font-size-base;
  color: $text-secondary;
  margin-bottom: $spacing-xxl;
}

.error-actions {
  display: flex;
  gap: $spacing-base;
  justify-content: center;
}

@media screen and (max-width: $breakpoint-sm) {
  .error-code {
    font-size: 80px;
  }

  .error-message {
    font-size: $font-size-lg;
  }

  .error-actions {
    flex-direction: column;
    align-items: center;
  }
}
</style>
