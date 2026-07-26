import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const usePermissionStore = defineStore(
  'permission',
  () => {
    const menus = ref(JSON.parse(localStorage.getItem('permission_menus') || '[]'))
    const permissions = ref(JSON.parse(localStorage.getItem('permission_codes') || '[]'))
    const buttons = ref(JSON.parse(localStorage.getItem('permission_buttons') || '[]'))

    function setMenus(menuList) {
      menus.value = menuList
      localStorage.setItem('permission_menus', JSON.stringify(menuList))
    }

    function setPermissions(permissionList) {
      permissions.value = permissionList
      localStorage.setItem('permission_codes', JSON.stringify(permissionList))
    }

    function setButtons(buttonList) {
      buttons.value = buttonList
      localStorage.setItem('permission_buttons', JSON.stringify(buttonList))
    }

    function hasPermission(permission) {
      if (!permission) return true
      if (permissions.value.includes(permission)) return true
      if (Array.isArray(permission)) {
        return permission.some(p => permissions.value.includes(p))
      }
      return false
    }

    function hasButton(button) {
      if (!button) return true
      if (buttons.value.includes(button)) return true
      if (Array.isArray(button)) {
        return button.some(b => buttons.value.includes(b))
      }
      return false
    }

    function clearPermissions() {
      menus.value = []
      permissions.value = []
      buttons.value = []
      localStorage.removeItem('permission_menus')
      localStorage.removeItem('permission_codes')
      localStorage.removeItem('permission_buttons')
    }

    return {
      menus,
      permissions,
      buttons,
      setMenus,
      setPermissions,
      setButtons,
      hasPermission,
      hasButton,
      clearPermissions
    }
  }
)
