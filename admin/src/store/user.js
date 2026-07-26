import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { login as loginApi, logout as logoutApi, getUserInfo as getUserInfoApi, refreshToken as refreshTokenApi } from '@/api/auth'

export const useUserStore = defineStore(
  'user',
  () => {
    const token = ref(sessionStorage.getItem('token') || '')
    const refreshToken = ref(sessionStorage.getItem('refreshToken') || '')
    const userInfo = ref(JSON.parse(localStorage.getItem('userInfo') || '{}'))
    const menus = ref(JSON.parse(localStorage.getItem('menus') || '[]'))
    const permissions = ref(JSON.parse(localStorage.getItem('permissions') || '[]'))

    const isLoggedIn = computed(() => !!token.value)
    const hasUserInfo = computed(() => !!userInfo.value && !!userInfo.value.id)

    function setToken(newToken) {
      token.value = newToken
      sessionStorage.setItem('token', newToken)
    }

    function setRefreshToken(newRefreshToken) {
      refreshToken.value = newRefreshToken
      sessionStorage.setItem('refreshToken', newRefreshToken)
    }

    function setUserInfo(info) {
      userInfo.value = info
      localStorage.setItem('userInfo', JSON.stringify(info))
    }

    function setMenus(menuList) {
      menus.value = menuList
      localStorage.setItem('menus', JSON.stringify(menuList))
    }

    function setPermissions(permissionList) {
      permissions.value = permissionList
      localStorage.setItem('permissions', JSON.stringify(permissionList))
    }

    function hasPermission(permission) {
      if (!permission) return true
      if (permissions.value.includes(permission)) return true
      if (Array.isArray(permission)) {
        return permission.some(p => permissions.value.includes(p))
      }
      return false
    }

    async function login(loginForm) {
      const res = await loginApi(loginForm)
      if (res.code === 0) {
        setToken(res.data.access_token)
        setRefreshToken(res.data.refresh_token)
        setUserInfo(res.data.user_info)
      }
      return res
    }

    async function fetchUserInfo() {
      const res = await getUserInfoApi()
      if (res.code === 0) {
        setUserInfo(res.data.user_info)
        setMenus(res.data.menus)
        setPermissions(res.data.permissions)
      }
      return res
    }

    async function refreshTokenFn() {
      const res = await refreshTokenApi({ refresh_token: refreshToken.value })
      if (res.code === 0) {
        setToken(res.data.access_token)
        if (res.data.refresh_token) {
          setRefreshToken(res.data.refresh_token)
        }
      }
      return res
    }

    async function logout() {
      try {
        await logoutApi()
      } catch (e) {
        console.error('登出请求失败:', e)
      }
      clearUserState()
    }

    function clearUserState() {
      token.value = ''
      refreshToken.value = ''
      userInfo.value = {}
      menus.value = []
      permissions.value = []
      sessionStorage.removeItem('token')
      sessionStorage.removeItem('refreshToken')
      localStorage.removeItem('userInfo')
      localStorage.removeItem('menus')
      localStorage.removeItem('permissions')
    }

    return {
      token,
      refreshToken,
      userInfo,
      menus,
      permissions,
      isLoggedIn,
      hasUserInfo,
      setToken,
      setRefreshToken,
      setUserInfo,
      setMenus,
      setPermissions,
      hasPermission,
      login,
      fetchUserInfo,
      refreshTokenFn,
      logout,
      clearUserState
    }
  }
)
