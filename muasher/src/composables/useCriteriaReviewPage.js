import { computed } from 'vue'
import { useCriteriaVersions } from '@/composables/useCriteriaVersions'
import { useConsistencyCheck } from '@/composables/usePublishCriteria'
import { usePublicationPreview } from '@/composables/usePublicationPreview'
import { useCriteriaMainNames } from '@/composables/useCriteriaMainNames'

// The data of the review-and-publish page: the draft, the version it replaces, what blocks it and
// what it changes. The steps follow from it: no changes step when nothing is published yet.
export function useCriteriaReviewPage() {
  const versions = useCriteriaVersions()
  const consistency = useConsistencyCheck()
  const preview = usePublicationPreview()
  const { mainNames, loadMainNames } = useCriteriaMainNames()

  const draft = versions.draft
  const published = versions.published
  const steps = computed(() =>
    published.value ? ['notes', 'changes', 'apply'] : ['notes', 'apply'],
  )

  async function load() {
    await versions.load()
    if (!draft.value) return
    consistency.check(draft.value.id).catch(() => null)
    if (published.value) preview.load(draft.value.id)
    loadMainNames(draft.value.id)
  }

  return { status: versions.status, draft, published, steps, consistency, preview, mainNames, load }
}
