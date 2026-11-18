import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export const useAppStore = defineStore('app', () => {
  const theme = ref(localStorage.getItem('rocket_theme') || 'light')
  const sidebarOpen = ref(false)
  const toasts = ref([])
  let toastId = 0

  function toggleTheme() {
    theme.value = theme.value === 'dark' ? 'light' : 'dark'
    localStorage.setItem('rocket_theme', theme.value)
    applyTheme()
  }

  function applyTheme() {
    document.documentElement.setAttribute('data-theme', theme.value)
  }

  function applyThemeColor() {
    const color = window.settings?.theme_color || 'default'
    if (color && color !== 'default') {
      document.documentElement.setAttribute('data-theme-color', color)
    } else {
      document.documentElement.removeAttribute('data-theme-color')
    }
  }

  function applyBackgroundUrl() {
    const url = window.settings?.background_url
    if (url) {
      document.documentElement.setAttribute('data-bg-custom', 'true')
      document.body.style.backgroundImage = `url(${url})`
      document.body.style.backgroundSize = 'cover'
      document.body.style.backgroundPosition = 'center'
      document.body.style.backgroundAttachment = 'fixed'
    }
  }

  function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value
  }

  function closeSidebar() {
    sidebarOpen.value = false
  }

  function showToast(message, type = 'info', duration = 3000) {
    const id = ++toastId
    toasts.value.push({ id, message, type })
    setTimeout(() => {
      toasts.value = toasts.value.filter(t => t.id !== id)
    }, duration)
    return id
  }

  // Initialize
  applyTheme()
  applyThemeColor()
  applyBackgroundUrl()

  return { theme, sidebarOpen, toasts, toggleTheme, applyTheme, toggleSidebar, closeSidebar, showToast }
})
