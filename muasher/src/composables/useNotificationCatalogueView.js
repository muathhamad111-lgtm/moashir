import { computed, reactive, toValue } from 'vue'
import { NOTIFICATION_MODULES } from '@/constants/notificationCatalogue'

function normalized(text) {
  return String(text ?? '')
    .toLocaleLowerCase()
    .trim()
}

function moduleOrder(types) {
  const extra = [...new Set(types.map((type) => type.module))].filter(
    (module) => !NOTIFICATION_MODULES.includes(module),
  )
  return [...NOTIFICATION_MODULES, ...extra]
}

/**
 * Filters the notification catalogue on the client (the API returns every type in one call)
 * and groups what is left by module, in the platform's module order.
 */
export function useNotificationCatalogueView(types, nameOf) {
  const filters = reactive({ search: '', audience: '' })

  function matchesSearch(type) {
    const term = normalized(filters.search)
    if (!term) return true
    return normalized(type.code).includes(term) || normalized(nameOf(type.code)).includes(term)
  }

  function matchesAudience(type) {
    return !filters.audience || type.audienceKinds.includes(filters.audience)
  }

  const visible = computed(() =>
    toValue(types).filter((type) => matchesSearch(type) && matchesAudience(type)),
  )

  const groups = computed(() =>
    moduleOrder(visible.value)
      .map((module) => ({ module, types: visible.value.filter((type) => type.module === module) }))
      .filter((group) => group.types.length),
  )

  return { filters, visible, groups }
}
