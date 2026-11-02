import { storeToRefs } from 'pinia'
import { useUiStore } from '@/stores/ui'

export function useTheme() {
  const ui = useUiStore()
  const { theme } = storeToRefs(ui)
  return { theme, setTheme: ui.setTheme, toggleTheme: ui.toggleTheme }
}
