import { computed, onMounted, ref, watch } from 'vue'
import { brandingService } from '@/services/brandingService'
import { useBrandingStore } from '@/stores/branding'
import { LOGO_DIMENSIONS, LOGO_SURFACES } from '@/constants/branding'
import { serverContrastPairs } from '@/utils/brandingContrast'
import { useBrandColorDraft } from './useBrandColorDraft'
import { logoForSurface } from './useBackOfficeLogo'
import { useLogoSelection } from './useLogoSelection'
import { useSuperAdminReason } from './useSuperAdminReason'

const CONTRAST_FAIL = 'contrast_fail'

function bySurface(build) {
  return Object.fromEntries(LOGO_SURFACES.map((surface) => [surface, build(surface)]))
}

export function useBrandingEditor({ auto = true } = {}) {
  const branding = useBrandingStore()
  const logos = bySurface((surface) => useLogoSelection(LOGO_DIMENSIONS[surface]))
  const { prompt: reasonPrompt, reasonExtra } = useSuperAdminReason()

  const draft = useBrandColorDraft()
  const savedLogos = ref(bySurface(() => null))
  const status = ref('loading')
  const saving = ref(false)
  const resetting = ref(false)
  const rejectedPairs = ref([])

  const busy = computed(() => saving.value || resetting.value)
  const hasPendingLogo = computed(() => LOGO_SURFACES.some((surface) => logos[surface].file.value))
  const isDirty = computed(() => draft.isChanged.value || hasPendingLogo.value)
  const canSave = computed(() => isDirty.value && draft.contrastOk.value && !busy.value)
  const previewLogos = computed(() =>
    bySurface(
      (surface) => logos[surface].previewUrl.value || logoForSurface(savedLogos.value, surface),
    ),
  )

  watch([draft.draftColors, draft.draftOverrides], () => {
    rejectedPairs.value = []
  })

  function applyTheme(theme) {
    draft.applySaved(theme)
    savedLogos.value = bySurface((surface) => theme.logos?.[surface] ?? null)
    branding.apply(theme)
  }

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await brandingService.get()
      applyTheme(data)
      status.value = 'success'
    } catch {
      status.value = 'error'
    }
  }

  async function uploadPendingLogos(reason) {
    for (const surface of LOGO_SURFACES) {
      const file = logos[surface].file.value
      if (file) await brandingService.uploadLogo(file, surface, reason)
    }
  }

  function rememberRejection(error) {
    if (error?.normalized?.code === CONTRAST_FAIL)
      rejectedPairs.value = serverContrastPairs(error.normalized.meta?.pairs)
    throw error
  }

  async function withReason(flag, work) {
    const extra = await reasonExtra()
    if (!extra) return false
    flag.value = true
    try {
      const { data } = await work(extra.reason ?? null)
      applyTheme(data)
      LOGO_SURFACES.forEach((surface) => logos[surface].clear())
      return true
    } catch (error) {
      return rememberRejection(error)
    } finally {
      flag.value = false
    }
  }

  function save() {
    if (!canSave.value) return Promise.resolve(false)
    return withReason(saving, async (reason) => {
      await uploadPendingLogos(reason)
      return brandingService.update({ ...draft.payload(), reason })
    })
  }

  function reset() {
    return withReason(resetting, (reason) => brandingService.reset({ reason }))
  }

  if (auto) onMounted(load)

  return {
    ...draft,
    rejectedPairs,
    isDirty,
    canSave,
    busy,
    previewLogos,
    logos,
    status,
    saving,
    resetting,
    reasonPrompt,
    load,
    save,
    reset,
  }
}
