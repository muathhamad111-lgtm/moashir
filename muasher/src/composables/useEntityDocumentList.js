import { computed, ref, toValue } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSignedDownload } from '@/composables/useSignedDownload'

/**
 * The rows of an entity's documents with their preview and download; `requestLink(document)` issues the signed link
 * of one document for the viewer at hand (staff or the entity itself).
 */
export function useEntityDocumentList(documents, keys, requestLink) {
  const { t } = useI18n()
  const downloads = useSignedDownload()
  const previewing = ref(null)

  const rows = computed(() =>
    keys.map((key) => ({
      key,
      label: t(`entities.documents.kinds.${key}`),
      file: toValue(documents)?.[key] ?? null,
    })),
  )
  const previewOpen = computed({
    get: () => previewing.value !== null,
    set: (open) => {
      if (!open) previewing.value = null
    },
  })

  function preview(row) {
    previewing.value = row
  }

  function previewLink() {
    return requestLink(previewing.value.key)
  }

  function download(row) {
    return downloads.download(row.key, () => requestLink(row.key))
  }

  return { rows, previewing, previewOpen, downloads, preview, previewLink, download }
}
