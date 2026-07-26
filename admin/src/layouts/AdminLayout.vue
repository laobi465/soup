<template>
  <el-container class="admin-layout">
    <el-aside :width="sidebarWidth" class="sidebar" :class="{ collapsed: sidebarCollapsed }">
      <div class="logo">
        <span v-if="!sidebarCollapsed" class="logo-text">卡密验证平台</span>
        <span v-else class="logo-text">卡密</span>
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
          <template v-for="menu in menus" :key="menu.path">
            <el-sub-menu v-if="menu.children && menu.children.length > 0" :index="menu.path">
              <template #title>
                <el-icon v-if="menu.meta?.icon">
                  <component :is="menu.meta.icon" />
                </el-icon>
                <span>{{ menu.meta?.title }}</span>
              </template>
              <el-menu-item
                v-for="child in menu.children"
                :key="child.path"
                :index="child.path"
              >
                <el-icon v-if="child.meta?.icon">
                  <component :is="child.meta.icon" />
                </el-icon>
                <template #title>{{ child.meta?.title }}</template>
              </el-menu-item>
            </el-sub-menu>
            <el-menu-item v-else :index="menu.path">
              <el-icon v-if="menu.meta?.icon">
                <component :is="menu.meta.icon" />
              </el-icon>
              <template #title>{{ menu.meta?.title }}</template>
            </el-menu-item>
          </template>
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
            <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item v-for="(item, index) in breadcrumbs" :key="index">
              {{ item }}
            </el-breadcrumb-item>
          </el-breadcrumb>
        </div>
        <div class="header-right">
          <el-tooltip content="消息" placement="bottom">
            <el-badge :value="unreadCount" :max="99" class="header-icon" @click="goMessage">
              <el-icon><Bell /></el-icon>
            </el-badge>
          </el-tooltip>
          <el-tooltip content="全屏" placement="bottom">
            <el-icon class="header-icon" @click="toggleFullscreen">
              <FullScreen v-if="!isFullscreen" />
              <Aim v-else />
            </el-icon>
          </el-tooltip>
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
                  个人中心
                </el-dropdown-item>
                <el-dropdown-item command="setting">
                  <el-icon><Setting /></el-icon>
                  系统设置
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

      <div v-if="showTabs" class="tabs-container">
        <el-scrollbar>
          <div class="tabs-list">
            <div
              v-for="tab in tabs"
              :key="tab.path"
              class="tab-item"
              :class="{ active: tab.path === activeMenu }"
              @click="handleTabClick(tab)"
            >
              <span>{{ tab.title }}</span>
              <el-icon
                v-if="tabs.length > 1"
                class="close-icon"
                @click.stop="handleTabClose(tab)"
              >
                <Close />
              </el-icon>
            </div>
          </div>
        </el-scrollbar>
      </div>

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
      <div class="mobile-logo">卡密验证平台</div>
      <el-menu
        :default-active="activeMenu"
        :collapse="false"
        router
        background-color="#1f2d3d"
        text-color="#bfcbd9"
        active-text-color="#409EFF"
        @select="handleMobileMenuSelect"
      >
        <template v-for="menu in menus" :key="menu.path">
          <el-sub-menu v-if="menu.children && menu.children.length > 0" :index="menu.path">
            <template #title>
              <el-icon v-if="menu.meta?.icon">
                <component :is="menu.meta.icon" />
              </el-icon>
              <span>{{ menu.meta?.title }}</span>
            </template>
            <el-menu-item
              v-for="child in menu.children"
              :key="child.path"
              :index="child.path"
            >
              <el-icon v-if="child.meta?.icon">
                <component :is="child.meta.icon" />
              </el-icon>
              <template #title>{{ child.meta?.title }}</template>
            </el-menu-item>
          </el-sub-menu>
          <el-menu-item v-else :index="menu.path">
            <el-icon v-if="menu.meta?.icon">
              <component :is="menu.meta.icon" />
            </el-icon>
            <template #title>{{ menu.meta?.title }}</template>
          </el-menu-item>
        </template>
      </el-menu>
    </el-drawer>
  </el-container>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
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
  Setting,
  SwitchButton,
  Bell,
  FullScreen,
  Aim,
  Close
} from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()
const userStore = useUserStore()

const isFullscreen = ref(false)
const mobileDrawerVisible = ref(false)
const isMobile = ref(false)
const unreadCount = ref(0)
const tabs = ref([])

const sidebarCollapsed = computed(() => appStore.sidebarCollapsed)
const sidebarWidth = computed(() => {
  if (isMobile.value) return '0px'
  return sidebarCollapsed.value ? '64px' : '220px'
})
const activeMenu = computed(() => route.path)
const username = computed(() => userStore.userInfo.username || userStore.userInfo.nickname || '管理员')
const menus = computed(() => {
  const menuList = userStore.menus || []
  if (menuList.length > 0) return menuList
  return [
    {
      path: '/dashboard',
      name: 'Dashboard',
      meta: { title: '控制台', icon: 'Odometer' }
    }
  ]
})
const showTabs = computed(() => appStore.showTabs)
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
    router.push('/profile')
  } else if (command === 'setting') {
    router.push('/system/config')
  }
}

function goMessage() {
  router.push('/messages')
}

function toggleFullscreen() {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen()
    isFullscreen.value = true
  } else {
    document.exitFullscreen()
    isFullscreen.value = false
  }
}

function addTab() {
  const title = route.meta?.title
  if (!title || route.meta?.hidden) return

  const exists = tabs.value.find(tab => tab.path === route.path)
  if (!exists) {
    tabs.value.push({
      path: route.path,
      title: title,
      name: route.name
    })
  }
}

function handleTabClick(tab) {
  router.push(tab.path)
}

function handleTabClose(tab) {
  const index = tabs.value.findIndex(t => t.path === tab.path)
  if (index > -1) {
    tabs.value.splice(index, 1)
    if (tab.path === activeMenu.value && tabs.value.length > 0) {
      const nextTab = tabs.value[Math.max(0, index - 1)]
      router.push(nextTab.path)
    }
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

function handleFullscreenChange() {
  isFullscreen.value = !!document.fullscreenElement
}

watch(
  () => route.path,
  () => {
    addTab()
  },
  { immediate: true }
)

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)
  document.addEventListener('fullscreenchange', handleFullscreenChange)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
  document.removeEventListener('fullscreenchange', handleFullscreenChange)
})
</script>

<style scoped lang="scss">
@import '@/styles/variables.scss';

.admin-layout {
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

.header-icon {
  font-size: 18px;
  cursor: pointer;
  color: $text-regular;
  transition: color 0.2s;

  &:hover {
    color: $primary-color;
  }
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

.tabs-container {
  height: $tabs-height;
  background-color: #fff;
  border-bottom: 1px solid $border-lighter;
  flex-shrink: 0;

  :deep(.el-scrollbar__wrap) {
    overflow-y: hidden;
  }
}

.tabs-list {
  display: flex;
  align-items: center;
  padding: 0 $spacing-xs;
  height: $tabs-height;
  gap: $spacing-xs;
}

.tab-item {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 12px;
  font-size: $font-size-sm;
  color: $text-regular;
  background-color: $border-extra-light;
  border-radius: $border-radius-sm;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s;

  &:hover {
    color: $primary-color;
  }

  &.active {
    background-color: rgba($primary-color, 0.1);
    color: $primary-color;
  }

  .close-icon {
    font-size: 12px;
    opacity: 0.6;
    transition: opacity 0.2s;

    &:hover {
      opacity: 1;
    }
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

  .tabs-container {
    display: none;
  }
}
</style>
