import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePathIdentityStore } from '@/stores/pathIdentity'
import { localizedText } from '@/utils/localizedText'

// The award is the advanced path; older screens still say «award».
const IDENTITY_KEY = Object.freeze({ award: 'advanced' })
const FALLBACK_KEYS = Object.freeze({ award: 'paths.names.award' })

function identityKeyOf(path) {
  return IDENTITY_KEY[path] ?? path
}

/**
 * Path names are fixed labels; descriptions are the ones edited in «إدارة المسارات», falling back
 * to the built-in text until they load.
 */
export function usePathNames() {
  const { t, locale } = useI18n()
  const store = usePathIdentityStore()

  onMounted(() => store.ensureLoaded())

  function edited(path, field) {
    return localizedText(store.byPath[identityKeyOf(path)]?.[field], locale.value)
  }

  function pathName(path) {
    return t(FALLBACK_KEYS[path] ?? `paths.labels.${path}`)
  }

  function pathDescription(path, fallbackKey = '') {
    return edited(path, 'description') || (fallbackKey ? t(fallbackKey) : '')
  }

  return { pathName, pathDescription }
}
