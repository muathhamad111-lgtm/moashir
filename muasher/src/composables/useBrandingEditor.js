import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { brandingService } from '@/services/brandingService'
import { useBrandingStore } from '@/stores/branding'
import { DEFAULT_ACCENT_TOKEN, LOGO_SURFACES } from '@/constants/branding'
import { accentContrastPairs, passesAllContrast } from '@/utils/brandingContrast'
import { logoForSurface } from './useBackOfficeLogo'
import { useLogoSelection } from './useLogoSelection'

function resolve(value, locale) {
  if (value === null || value === undefined) return ''
  if (typeof value === 'string') return value
  return value[locale] || value.ar || value.en || ''
}

function bySurface(build) {
  return Object.fromEntries(LOGO_SURFACES.map((surface) => [surface, build(surface)]))
}

export function useBrandingEditor({ auto = true } = {}) {
  const { locale } = useI18n()
  const branding = useBrandingStore()
  const logos = bySurface(() => useLogoSelection())

  const palette = ref([])
  const savedToken = ref(DEFAULT_ACCENT_TOKEN)
  const draftToken = ref(DEFAULT_ACCENT_TOKEN)
  const savedLogos = ref(bySurface(() => null))
  const status = ref('loading')
  const saving = ref(false)
  const resetting = ref(false)

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

  function localized(value) {
    return resolve(value, locale.value)
  }

  function clearSelections() {
    LOGO_SURFACES.forEach((surface) => logos[surface].clear())
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

  async function uploadPendingLogos() {
    for (const surface of LOGO_SURFACES) {
      const file = logos[surface].file.value
      if (file) await brandingService.uploadLogo(file, surface)
    }
  }

  async function save() {
    if (!canSave.value) return false
    saving.value = true
    try {
      await uploadPendingLogos()
      const { data } = await brandingService.update({ accentToken: draftToken.value })
      applyTheme(data)
      clearSelections()
      return true
    } finally {
      saving.value = false
    }
  }

  async function reset() {
    resetting.value = true
    try {
      const { data } = await brandingService.reset()
      applyTheme(data)
      clearSelections()
      return true
    } finally {
      resetting.value = false
    }
  }

  if (auto) onMounted(load)

  return {
    palette,
    draftToken,
    savedToken,
    selectedEntry,
    contrastPairs,
    contrastOk,
    isDirty,
    canSave,
    busy,
    previewLogos,
    customLogos,
    logos,
    status,
    saving,
    resetting,
    localized,
    load,
    save,
    reset,
  }
}
