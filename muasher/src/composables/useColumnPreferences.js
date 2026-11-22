import { ref } from 'vue'
import { readStored, writeStored } from '@/utils/safeStorage'

// Which optional columns a list shows. The choice is a per-browser convenience, so a missing or
// unreadable stored value simply falls back to the defaults.
function storedColumns(storageKey, toggleable) {
  try {
    const parsed = JSON.parse(readStored(storageKey) ?? 'null')
    return Array.isArray(parsed) ? parsed.filter((key) => toggleable.includes(key)) : null
  } catch {
    return null
  }
}

export function useColumnPreferences({ storageKey, toggleable, hiddenByDefault = [] }) {
  const defaults = toggleable.filter((key) => !hiddenByDefault.includes(key))
  const visible = ref(storedColumns(storageKey, toggleable) ?? defaults)

  function isVisible(key) {
    return !toggleable.includes(key) || visible.value.includes(key)
  }

  function setVisible(key, shown) {
    const next = new Set(visible.value)
    if (shown) next.add(key)
    else next.delete(key)
    visible.value = toggleable.filter((column) => next.has(column))
    writeStored(storageKey, JSON.stringify(visible.value))
  }

  function reset() {
    visible.value = defaults
    writeStored(storageKey, JSON.stringify(defaults))
  }

  return { visible, isVisible, setVisible, reset }
}
