import { defineStore } from 'pinia'
import { DEFAULT_THEME, THEMES } from '@/constants/themes'
import { STORAGE_KEYS } from '@/constants/storageKeys'

function initialTheme() {
  const stored = localStorage.getItem(STORAGE_KEYS.theme)
  return Object.values(THEMES).includes(stored) ? stored : DEFAULT_THEME
}

export const useUiStore = defineStore('ui', {
  state: () => ({
    theme: initialTheme(),
    sidebarCollapsed: false,
  }),
  actions: {
    setTheme(next) {
      if (!Object.values(THEMES).includes(next)) return
      this.theme = next
      localStorage.setItem(STORAGE_KEYS.theme, next)
    },
    toggleTheme() {
      this.setTheme(this.theme === THEMES.dark ? THEMES.light : THEMES.dark)
    },
    toggleSidebar() {
      this.sidebarCollapsed = !this.sidebarCollapsed
    },
  },
})
