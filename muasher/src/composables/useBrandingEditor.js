import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { brandingService } from '@/services/brandingService'
import { useBrandingStore } from '@/stores/branding'
import { DEFAULT_ACCENT_TOKEN, LOGO_SURFACES } from '@/constants/branding'
import {
  accentContrastPairs,
  passesAllContrast,
  serverContrastPairs,
} from '@/utils/brandingContrast'
import { localizedText } from '@/utils/localizedText'
import { logoForSurface } from './useBackOfficeLogo'
import { useLogoSelection } from './useLogoSelection'
import { useSuperAdminReason } from './useSuperAdminReason'

const CONTRAST_FAIL = 'contrast_fail'

function bySurface(build) {
  return Object.fromEntries(LOGO_SURFACES.map((surface) => [surface, build(surface)]))
}

export function useBrandingEditor({ auto = true } = {}) {
  const { locale } = useI18n()
  const branding = useBrandingStore()
  const logos = bySurface(() => useLogoSelection())
  const { prompt: reasonPrompt, reasonExtra } = useSuperAdminReason()

  const palette = ref([])
  const savedToken = ref(DEFAULT_ACCENT_TOKEN)
  const draftToken = ref(DEFAULT_ACCENT_TOKEN)
  const savedLogos = ref(bySurface(() => null))
  const status = ref('loading')
  const saving = ref(false)
  const resetting = ref(false)
  const rejectedPairs = ref([])

  const busy = computed(() => saving.value || resetting.value)
  const selectedEntry = computed(
    () => palette.value.find((entry) => entry.token === draftToken.value) ?? null,
  )
  const contrastPairs = computed(() => accentContrastPairs(selectedEntry.value))
  const contrastOk = computed(() => passesAllContrast(contrastPairs.value))
  const hasPendingLogo = computed(() => LOGO_SURFACES.some((surface) => logos[surface].file.value))
  const isDirty = computed(() => draftToken.value !== savedToken.value || hasPendingLogo.value)
  const canSave = computed(() => isDirty.value && contrastOk.value && !busy.value)
  const previewLogos = computed(() =>
    bySurface(
      (surface) => logos[surface].previewUrl.value || logoForSurface(savedLogos.value, surface),
    ),
  )
  const customLogos = computed(() => bySurface((surface) => Boolean(savedLogos.value[surface])))

  watch(draftToken, () => {
    rejectedPairs.value = []
  })

  function localized(value) {
    return localizedText(value, locale.value)
  }

  function applyTheme(theme) {
    palette.value = theme.palette
    savedToken.value = theme.accentToken
    draftToken.value = theme.accentToken
    savedLogos.value = { ...bySurface(() => null), ...theme.logos }
    branding.apply(theme)
  }

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await brandingService.get()
      applyTheme(data)
      status.value = data.palette.length ? 'success' : 'empty'
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
      return brandingService.update({ accentToken: draftToken.value, reason })
    })
  }

  function reset() {
    return withReason(resetting, (reason) => brandingService.reset({ reason }))
  }

  if (auto) onMounted(load)

  return {
    palette,
    draftToken,
    savedToken,
    selectedEntry,
    contrastPairs,
    contrastOk,
    rejectedPairs,
    isDirty,
    canSave,
    busy,
    previewLogos,
    customLogos,
    logos,
    status,
    saving,
    resetting,
    reasonPrompt,
    localized,
    load,
    save,
    reset,
  }
}
