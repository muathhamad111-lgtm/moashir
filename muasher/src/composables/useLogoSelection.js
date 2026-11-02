import { onBeforeUnmount, ref } from 'vue'
import { LOGO_RULES } from '@/constants/branding'

export function logoErrorKey(file) {
  if (!file) return ''
  if (!LOGO_RULES.mimeTypes.includes(file.type)) return 'branding.logo.invalidType'
  if (file.size > LOGO_RULES.maxBytes) return 'branding.logo.tooLarge'
  return ''
}

export function useLogoSelection() {
  const file = ref(null)
  const previewUrl = ref(null)
  const errorKey = ref('')

  function revoke() {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = null
  }

  function clear() {
    revoke()
    file.value = null
    errorKey.value = ''
  }

  function select(candidate) {
    clear()
    errorKey.value = logoErrorKey(candidate)
    if (errorKey.value) return false
    file.value = candidate
    previewUrl.value = URL.createObjectURL(candidate)
    return true
  }

  onBeforeUnmount(revoke)

  return { file, previewUrl, errorKey, select, clear }
}
