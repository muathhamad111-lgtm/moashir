import { ref, shallowRef } from 'vue'
import { criteriaService } from '@/services/criteriaService'

export function usePublicationPreview() {
  const preview = shallowRef(null)
  const loading = ref(false)
  const failed = ref(false)

  async function load(versionId) {
    loading.value = true
    failed.value = false
    try {
      const envelope = await criteriaService.previewPublication(versionId)
      preview.value = envelope?.data ?? null
    } catch {
      preview.value = null
      failed.value = true
    } finally {
      loading.value = false
    }
  }

  return { preview, loading, failed, load }
}
