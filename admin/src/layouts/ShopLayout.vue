<template>
  <div class="shop-layout">
    <header class="shop-header">
      <div class="header-container">
        <div class="header-left" @click="goHome">
          <div class="logo">
            <el-icon :size="28"><Shop /></el-icon>
          </div>
          <span class="shop-name">{{ shopInfo.name || '发卡平台' }}</span>
        </div>
        <nav class="header-nav">
          <router-link
            v-for="nav in navItems"
            :key="nav.path"
            :to="nav.path"
            class="nav-item"
          >
            {{ nav.title }}
          </router-link>
        </nav>
        <div class="header-right">
          <router-link to="query" class="query-link">
            <el-icon><Search /></el-icon>
            <span>订单查询</span>
          </router-link>
        </div>
      </div>
    </header>

    <main class="shop-main">
      <router-view v-slot="{ Component }">
        <transition name="fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </main>

    <footer class="shop-footer">
      <div class="footer-container">
        <div class="footer-content">
          <p>{{ shopInfo.name || '发卡平台' }}</p>
          <p class="footer-desc">专业的卡密销售与验证平台</p>
        </div>
        <div class="footer-links">
          <a href="javascript:void(0)">帮助中心</a>
          <a href="javascript:void(0)">服务条款</a>
          <a href="javascript:void(0)">隐私政策</a>
        </div>
        <div class="footer-copyright">
          <p>Copyright {{ currentYear }} 版权所有</p>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Shop, Search } from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()

const shopInfo = ref({
  name: '卡密销售平台'
})

const currentYear = computed(() => new Date().getFullYear())

const navItems = [
  { path: '', title: '首页' },
  { path: 'products', title: '商品列表' }
]

function goHome() {
  router.push('')
}
</script>

<style scoped lang="scss">
@import '@/styles/variables.scss';

.shop-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: $background-page;
}

.shop-header {
  background-color: #fff;
  box-shadow: $shadow-light;
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 $spacing-xl;
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.header-left {
  display: flex;
  align-items: center;
  gap: $spacing-sm;
  cursor: pointer;
}

.logo {
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, $primary-color, $primary-color-light);
  border-radius: $border-radius-md;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
}

.shop-name {
  font-size: $font-size-lg;
  font-weight: $font-weight-bold;
  color: $text-primary;
}

.header-nav {
  display: flex;
  align-items: center;
  gap: $spacing-xl;

  .nav-item {
    font-size: $font-size-base;
    color: $text-regular;
    text-decoration: none;
    padding: 8px 0;
    position: relative;
    transition: color 0.2s;

    &:hover,
    &.router-link-active {
      color: $primary-color;

      &::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 2px;
        background-color: $primary-color;
        border-radius: 1px;
      }
    }
  }
}

.header-right {
  display: flex;
  align-items: center;
  gap: $spacing-md;
}

.query-link {
  display: flex;
  align-items: center;
  gap: 6px;
  color: $text-regular;
  text-decoration: none;
  font-size: $font-size-sm;
  padding: 6px 12px;
  border-radius: $border-radius-base;
  transition: all 0.2s;

  &:hover {
    color: $primary-color;
    background-color: rgba($primary-color, 0.05);
  }
}

.shop-main {
  flex: 1;
  max-width: 1200px;
  margin: 0 auto;
  width: 100%;
  padding: $spacing-xl;
}

.shop-footer {
  background-color: #fff;
  border-top: 1px solid $border-lighter;
  margin-top: auto;
}

.footer-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: $spacing-xl;
  text-align: center;
}

.footer-content {
  margin-bottom: $spacing-md;

  p {
    color: $text-primary;
    font-size: $font-size-base;
    margin: 0 0 4px 0;
    font-weight: $font-weight-medium;
  }

  .footer-desc {
    color: $text-secondary;
    font-size: $font-size-sm;
    font-weight: $font-weight-normal;
  }
}

.footer-links {
  display: flex;
  justify-content: center;
  gap: $spacing-lg;
  margin-bottom: $spacing-md;

  a {
    color: $text-secondary;
    font-size: $font-size-sm;
    text-decoration: none;
    transition: color 0.2s;

    &:hover {
      color: $primary-color;
    }
  }
}

.footer-copyright {
  p {
    color: $text-placeholder;
    font-size: $font-size-xs;
    margin: 0;
  }
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@media screen and (max-width: $breakpoint-sm) {
  .header-container {
    padding: 0 $spacing-sm;
    height: 56px;
  }

  .shop-name {
    font-size: $font-size-base;
  }

  .header-nav {
    display: none;
  }

  .shop-main {
    padding: $spacing-sm;
  }

  .footer-container {
    padding: $spacing-base;
  }

  .footer-links {
    flex-wrap: wrap;
    gap: $spacing-sm;
  }
}
</style>
