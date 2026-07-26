<template>
  <el-container class="agent-layout">
    <el-aside :width="sidebarWidth" class="sidebar" :class="{ collapsed: sidebarCollapsed }">
      <div class="logo">
        <span v-if="!sidebarCollapsed" class="logo-text">代理后台</span>
        <span v-else class="logo-text">代理</span>
      </div>
      <el-scrollbar class="menu-scroll">
        <el-menu
          :default-active="activeMenu"
          :collapse="sidebarCollapsed"
          :unique-opened="true"
          router
          background-color="#1f2d3d"
          text-color="#bfcbd9"
          active-text-color="#409EFF"
        >
          <el-menu-item index="/agent/dashboard">
            <el-icon><Odometer /></el-icon>
            <template #title>控制台</template>
          </el-menu-item>
          <el-menu-item index="/agent/team">
            <el-icon><UserFilled /></el-icon>
            <template #title>我的团队</template>
          </el-menu-item>
          <el-menu-item index="/agent/commission">
            <el-icon><Money /></el-icon>
            <template #title>佣金明细</template>
          </el-menu-item>
          <el-menu-item index="/agent/wallet">
            <el-icon><Wallet /></el-icon>
            <template #title>我的钱包</template>
          </el-menu-item>
        </el-menu>
      </el-scrollbar>
    </el-aside>

    <el-container class="main-container">
      <el-header class="header">
        <div class="header-left">
          <el-icon class="collapse-btn" @click="toggleSidebar">
            <Fold v-if="!sidebarCollapsed" />
            <Expand v-else />
          </el-icon>
          <el-breadcrumb separator="/" class="breadcrumb">
            <el-breadcrumb-item :to="{ path: '/agent/dashboard' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item v-for="(item, index) in breadcrumbs" :key="index">
              {{ item }}
            </el-breadcrumb-item>
          </el-breadcrumb>
        </div>
        <div class="header-right">
          <el-dropdown @command="handleCommand" trigger="click">
            <span class="user-info">
              <el-avatar :size="32" :icon="UserFilled" />
              <span class="username">{{ username }}</span>
              <el-icon><ArrowDown /></el-icon>
            </span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="profile">
                  <el-icon><User /></el-icon>
                  个人信息
                </el-dropdown-item>
                <el-dropdown-item command="logout" divided>
                  <el-icon><SwitchButton /></el-icon>
                  退出登录
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </el-header>

      <el-main class="main-content">
        <router-view v-slot="{ Component }">
          <transition name="fade-transform" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </el-main>
    </el-container>

    <el-drawer
      v-model="mobileDrawerVisible"
      direction="ltr"
      size="80%"
      :with-header="false"
      class="mobile-drawer"
    >
      <div class="mobile-logo">代理后台</div>
      <el-menu
        :default-active="activeMenu"
        router
        background-color="#1f2d3d"
        text-color="#bfcbd9"
        active-text-color="#409EFF"
        @select="handleMobileMenuSelect"
      >
        <el-menu-item index="/agent/dashboard">
          <el-icon><Odometer /></el-icon>
          <template #title>控制台</template>
        </el-menu-item>
        <el-menu-item index="/agent/team">
          <el-icon><UserFilled /></el-icon>
          <template #title>我的团队</template>
        </el-menu-item>
        <el-menu-item index="/agent/commission">
          <el-icon><Money /></el-icon>
          <template #title>佣金明细</template>
        </el-menu-item>
        <el-menu-item index="/agent/wallet">
          <el-icon><Wallet /></el-icon>
          <template #title>我的钱包</template>
        </el-menu-item>
      </el-menu>
    </el-drawer>
  </el-container>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAppStore } from '@/store/app'
import { useUserStore } from '@/store/user'
import { ElMessageBox, ElMessage } from 'element-plus'
import {
  Fold,
  Expand,
  ArrowDown,
  UserFilled,
  User,
  SwitchButton,
  Odometer,
  Money,
  Wallet
} from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()
const userStore = useUserStore()

const mobileDrawerVisible = ref(false)
const isMobile = ref(false)

const sidebarCollapsed = computed(() => appStore.sidebarCollapsed)
const sidebarWidth = computed(() => {
  if (isMobile.value) return '0px'
  return sidebarCollapsed.value ? '64px' : '220px'
})
const activeMenu = computed(() => route.path)
const username = computed(() => userStore.userInfo.username || userStore.userInfo.nickname || '代理商')
const breadcrumbs = computed(() => {
  const matched = route.matched.filter(item => item.meta && item.meta.title)
  return matched.map(item => item.meta.title)
})

function toggleSidebar() {
  if (isMobile.value) {
    mobileDrawerVisible.value = true
  } else {
    appStore.toggleSidebar()
  }
}

function handleCommand(command) {
  if (command === 'logout') {
    ElMessageBox.confirm('确定要退出登录吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }).then(() => {
      userStore.logout()
      router.push('/login')
      ElMessage.success('已退出登录')
    })
  } else if (command === 'profile') {
    ElMessage.info('个人信息功能开发中')
  }
}

function handleMobileMenuSelect() {
  mobileDrawerVisible.value = false
}

function checkMobile() {
  isMobile.value = window.innerWidth < 768
  if (isMobile.value && !sidebarCollapsed.value) {
    appStore.setSidebarCollapsed(true)
  }
}

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
})
</script>

<style scoped lang="scss">
@import '@/styles/variables.scss';

.agent-layout {
  height: 100vh;
  overflow: hidden;
}

.sidebar {
  background-color: #1f2d3d;
  transition: width 0.3s ease;
  overflow: hidden;
  display: flex;
  flex-direction: column;

  &.collapsed {
    .logo-text {
      font-size: 14px;
    }
  }
}

.logo {
  height: $header-height;
  line-height: $header-height;
  text-align: center;
  color: #fff;
  font-size: 18px;
  font-weight: bold;
  border-bottom: 1px solid #2d3e53;
  white-space: nowrap;
  overflow: hidden;
}

.menu-scroll {
  flex: 1;
  overflow: hidden;

  :deep(.el-scrollbar__wrap) {
    overflow-x: hidden;
  }
}

:deep(.el-menu) {
  border-right: none;
}

.main-container {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.header {
  background-color: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 $spacing-base;
  box-shadow: $shadow-lighter;
  height: $header-height;
  flex-shrink: 0;
}

.header-left {
  display: flex;
  align-items: center;
  gap: $spacing-base;
  flex: 1;
  min-width: 0;
}

.collapse-btn {
  font-size: 20px;
  cursor: pointer;
  color: $text-primary;
  transition: color 0.2s;
  flex-shrink: 0;

  &:hover {
    color: $primary-color;
  }
}

.breadcrumb {
  flex: 1;
  min-width: 0;
}

.header-right {
  display: flex;
  align-items: center;
  gap: $spacing-md;
  flex-shrink: 0;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  padding: 0 8px;

  .username {
    color: $text-primary;
    font-size: $font-size-sm;
    max-width: 100px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.main-content {
  background-color: $background-page;
  padding: $spacing-base;
  overflow-y: auto;
  flex: 1;
}

.fade-transform-enter-active,
.fade-transform-leave-active {
  transition: all 0.3s;
}

.fade-transform-enter-from {
  opacity: 0;
  transform: translateX(-20px);
}

.fade-transform-leave-to {
  opacity: 0;
  transform: translateX(20px);
}

.mobile-drawer {
  :deep(.el-drawer) {
    background-color: #1f2d3d;
  }
}

.mobile-logo {
  height: $header-height;
  line-height: $header-height;
  text-align: center;
  color: #fff;
  font-size: 18px;
  font-weight: bold;
  border-bottom: 1px solid #2d3e53;
}

@media screen and (max-width: $breakpoint-sm) {
  .header {
    padding: 0 $spacing-sm;
  }

  .username {
    display: none;
  }

  .breadcrumb {
    display: none;
  }

  .main-content {
    padding: $spacing-sm;
  }
}
</style>
