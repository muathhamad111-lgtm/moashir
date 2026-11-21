import { onBeforeUnmount, ref } from 'vue'
import { LOGO_RULES } from '@/constants/branding'
import { dimensionsMatch, readImageDimensions } from '@/utils/branding/imageDimensions'

export function logoErrorKey(file) {
  if (!file) return ''
  if (!LOGO_RULES.mimeTypes.includes(file.type)) return 'branding.logo.invalidType'
  if (file.size > LOGO_RULES.maxBytes) return 'branding.logo.tooLarge'
  return ''
}

export function useLogoSelection(expectedDimensions = null) {
  const file = ref(null)
  const previewUrl = ref(null)
  const errorKey = ref('')
  const mismatchedDimensions = ref(null)

  function revoke() {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = null
  }

  function clear() {
    revoke()
    file.value = null
    errorKey.value = ''
    mismatchedDimensions.value = null
  }

  async function checkDimensions(candidate) {
    if (!expectedDimensions) return
    const actual = await readImageDimensions(candidate)
    if (file.value !== candidate || !actual) return
    mismatchedDimensions.value = dimensionsMatch(actual, expectedDimensions) ? null : actual
  }

  function select(candidate) {
    clear()
    errorKey.value = logoErrorKey(candidate)
    if (errorKey.value) return false
    file.value = candidate
    previewUrl.value = URL.createObjectURL(candidate)
    checkDimensions(candidate)
    return true
  }

  onBeforeUnmount(revoke)

  return { file, previewUrl, errorKey, mismatchedDimensions, select, clear }
}
