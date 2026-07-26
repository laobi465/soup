import { useUserStore } from '@/store/user'

export const permissionDirective = {
  mounted(el, binding) {
    checkPermission(el, binding)
  },
  updated(el, binding) {
    checkPermission(el, binding)
  }
}

function checkPermission(el, binding) {
  const { value } = binding
  const userStore = useUserStore()

  if (!value) {
    return
  }

  const hasPermission = userStore.hasPermission(value)

  if (!hasPermission) {
    if (el.parentNode) {
      el.parentNode.removeChild(el)
    } else {
      el.style.display = 'none'
    }
  }
}

export default permissionDirective
