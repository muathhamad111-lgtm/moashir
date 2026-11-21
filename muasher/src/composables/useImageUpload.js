import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { CMS_IMAGE_RULES } from '@/constants/cms'

function imageErrorKey(file, rules = CMS_IMAGE_RULES) {
  if (!file) return ''
  if (!rules.mimeTypes.includes(file.type)) return 'cms.image.invalidType'
  if (file.size > rules.maxBytes) return 'cms.image.tooLarge'
  return ''
}

export function useImageUpload(upload, rules = CMS_IMAGE_RULES) {
  const { t } = useI18n()
  const uploading = ref(false)
  const error = ref('')

  async function send(file) {
    const key = imageErrorKey(file, rules)
    error.value = key ? t(key) : ''
    if (key) return null
    uploading.value = true
    try {
      const { data } = await upload(file)
      return { fileId: data.fileId, url: data.url }
    } catch (failure) {
      error.value =
        failure?.normalized?.fieldErrors?.file?.[0] ??
        failure?.normalized?.message ??
        t('errors.unexpected')
      return null
    } finally {
      uploading.value = false
    }
  }

  return { uploading, error, send }
}
