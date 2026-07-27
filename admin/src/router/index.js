import { createRouter, createWebHistory } from 'vue-router'
import { useUserStore } from '@/store/user'
import { usePermissionStore } from '@/store/permission'

const constantRoutes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/login/index.vue'),
    meta: { title: '登录', requiresAuth: false, hidden: true }
  },
  {
    path: '/403',
    name: 'Forbidden',
    component: () => import('@/views/error/403.vue'),
    meta: { title: '无权限', requiresAuth: false, hidden: true }
  },
  {
    path: '/404',
    name: 'NotFound',
    component: () => import('@/views/error/404.vue'),
    meta: { title: '页面不存在', requiresAuth: false, hidden: true }
  },
  {
    path: '/shop/:merchantNo',
    name: 'ShopHome',
    component: () => import('@/layouts/ShopLayout.vue'),
    redirect: '/shop/:merchantNo/',
    meta: { title: '店铺首页', requiresAuth: false, hidden: true },
    children: [
      {
        path: '',
        name: 'ShopIndex',
        component: () => import('@/views/shop/index.vue'),
        meta: { title: '首页', requiresAuth: false, hidden: true }
      },
      {
        path: 'product/:id',
        name: 'ShopProduct',
        component: () => import('@/views/shop/product.vue'),
        meta: { title: '商品详情', requiresAuth: false, hidden: true }
      },
      {
        path: 'order',
        name: 'ShopOrder',
        component: () => import('@/views/shop/order.vue'),
        meta: { title: '确认订单', requiresAuth: false, hidden: true }
      },
      {
        path: 'success',
        name: 'ShopSuccess',
        component: () => import('@/views/shop/success.vue'),
        meta: { title: '支付成功', requiresAuth: false, hidden: true }
      },
      {
        path: 'query',
        name: 'ShopQuery',
        component: () => import('@/views/shop/query.vue'),
        meta: { title: '订单查询', requiresAuth: false, hidden: true }
      }
    ]
  },
  {
    path: '/',
    component: () => import('@/layouts/AdminLayout.vue'),
    redirect: '/dashboard',
    meta: { requiresAuth: true },
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/dashboard/index.vue'),
        meta: { title: '控制台', icon: 'Odometer' }
      },
      {
        path: 'apk-inject',
        name: 'ApkInject',
        component: () => import('@/views/merchant/apk-inject/index.vue'),
        meta: { title: 'APK注入', requiresAuth: true }
      },
      {
        path: 'apk-inject/create',
        name: 'ApkInjectCreate',
        component: () => import('@/views/merchant/apk-inject/create.vue'),
        meta: { title: '创建注入任务', requiresAuth: true, hidden: true }
      }
    ]
  },
  {
    path: '/agent',
    component: () => import('@/layouts/AgentLayout.vue'),
    redirect: '/agent/dashboard',
    meta: { requiresAuth: true, role: 'agent' },
    children: [
      {
        path: 'dashboard',
        name: 'AgentDashboard',
        component: () => import('@/views/agent/dashboard/index.vue'),
        meta: { title: '控制台', icon: 'Odometer' }
      }
    ]
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/404'
  }
]

const whiteList = ['/login', '/403', '/404', '/shop']

const router = createRouter({
  history: createWebHistory(),
  routes: constantRoutes
})

const modules = import.meta.glob('../views/**/*.vue')

function loadView(viewPath) {
  const fullPath = `../views/${viewPath}.vue`
  return modules[fullPath] || (() => import('@/views/error/404.vue'))
}

function generateRoutes(menus) {
  const routes = []
  for (const menu of menus) {
    const route = {
      path: menu.path,
      name: menu.name,
      component: menu.component === 'layout/AdminLayout'
        ? () => import('@/layouts/AdminLayout.vue')
        : menu.component === 'layout/AgentLayout'
          ? () => import('@/layouts/AgentLayout.vue')
          : loadView(menu.component),
      meta: menu.meta || {}
    }
    if (menu.children && menu.children.length > 0) {
      route.children = generateRoutes(menu.children)
    }
    routes.push(route)
  }
  return routes
}

function isInWhiteList(path) {
  return whiteList.some(item => path.startsWith(item))
}

function hasPermission(route, permissions) {
  if (route.meta && route.meta.permission) {
    if (Array.isArray(route.meta.permission)) {
      return route.meta.permission.some(p => permissions.includes(p))
    }
    return permissions.includes(route.meta.permission)
  }
  return true
}

function filterAsyncRoutes(routes, permissions) {
  const res = []
  routes.forEach(route => {
    const tmp = { ...route }
    if (hasPermission(tmp, permissions)) {
      if (tmp.children) {
        tmp.children = filterAsyncRoutes(tmp.children, permissions)
      }
      res.push(tmp)
    }
  })
  return res
}

let dynamicRoutesAdded = false

router.beforeEach(async (to, from, next) => {
  const userStore = useUserStore()
  const permissionStore = usePermissionStore()
  const token = userStore.token
  const hasUserInfo = userStore.hasUserInfo

  document.title = to.meta.title ? `${to.meta.title} - 卡密验证平台` : '卡密验证平台'

  if (isInWhiteList(to.path)) {
    if (to.path === '/login' && token) {
      next({ path: '/' })
    } else {
      next()
    }
    return
  }

  if (!token) {
    next({ path: '/login', query: { redirect: to.fullPath } })
    return
  }

  if (hasUserInfo) {
    if (to.meta && to.meta.permission) {
      if (!userStore.hasPermission(to.meta.permission)) {
        next({ path: '/403' })
        return
      }
    }
    next()
    return
  }

  try {
    const res = await userStore.fetchUserInfo()
    if (res.code === 0) {
      if (!dynamicRoutesAdded && res.data.menus && res.data.menus.length > 0) {
        permissionStore.setMenus(res.data.menus)
        permissionStore.setPermissions(res.data.permissions || [])
        permissionStore.setButtons(res.data.buttons || [])

        const accessedRoutes = filterAsyncRoutes(generateRoutes(res.data.menus), res.data.permissions || [])
        for (const route of accessedRoutes) {
          router.addRoute(route)
        }
        dynamicRoutesAdded = true
      }
      next({ ...to, replace: true })
    } else {
      userStore.clearUserState()
      next({ path: '/login', query: { redirect: to.fullPath } })
    }
  } catch (error) {
    console.error('获取用户信息失败:', error)
    userStore.clearUserState()
    next({ path: '/login', query: { redirect: to.fullPath } })
  }
})

router.afterEach(() => {
})

export function resetRouter() {
  dynamicRoutesAdded = false
  const allRoutes = router.getRoutes()
  allRoutes.forEach(route => {
    if (route.name && !constantRoutes.find(r => r.name === route.name)) {
      router.removeRoute(route.name)
    }
  })
}

export default router
