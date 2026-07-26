import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useAppStore = defineStore(
  'app',
  () => {
    const sidebarCollapsed = ref(localStorage.getItem('sidebarCollapsed') === 'true')
    const theme = ref(localStorage.getItem('theme') || 'light')
    const language = ref(localStorage.getItem('language') || 'zh-CN')
    const showTabs = ref(localStorage.getItem('showTabs') !== 'false')

    function toggleSidebar() {
      sidebarCollapsed.value = !sidebarCollapsed.value
      localStorage.setItem('sidebarCollapsed', sidebarCollapsed.value)
    }

    function setSidebarCollapsed(value) {
      sidebarCollapsed.value = value
      localStorage.setItem('sidebarCollapsed', value)
    }

    function setTheme(newTheme) {
      theme.value = newTheme
      localStorage.setItem('theme', newTheme)
      document.documentElement.setAttribute('data-theme', newTheme)
    }

    function toggleTheme() {
      setTheme(theme.value === 'light' ? 'dark' : 'light')
    }

    function setLanguage(lang) {
      language.value = lang
      localStorage.setItem('language', lang)
    }

    function toggleShowTabs() {
      showTabs.value = !showTabs.value
      localStorage.setItem('showTabs', showTabs.value)
    }

    function setShowTabs(value) {
      showTabs.value = value
      localStorage.setItem('showTabs', value)
    }

    return {
      sidebarCollapsed,
      theme,
      language,
      showTabs,
      toggleSidebar,
      setSidebarCollapsed,
      setTheme,
      toggleTheme,
      setLanguage,
      toggleShowTabs,
      setShowTabs
    }
  }
)
