import { computed, ref } from 'vue'
import { DEFAULT_BRAND_COLORS, DERIVED_BRAND_COLORS } from '@/constants/branding'
import { deriveBrandScheme, normalizeHex } from '@/utils/brandingContrast'

function sameColors(first, second) {
  return (
    normalizeHex(first.primary) === normalizeHex(second.primary) &&
    normalizeHex(first.secondary) === normalizeHex(second.secondary)
  )
}

function sameOverrides(first, second) {
  return DERIVED_BRAND_COLORS.every(
    (key) => (normalizeHex(first[key]) ?? null) === (normalizeHex(second[key]) ?? null),
  )
}

function without(overrides, key) {
  return Object.fromEntries(Object.entries(overrides).filter(([name]) => name !== key))
}

// Everything the screen derives from the draft: the scheme and the value each colour would take
// back on auto. There is no contrast check: the client owns the colours it chooses; only a
// malformed base colour (no scheme) keeps the draft from being saved.
function useDraftScheme(draftColors, draftOverrides) {
  const draftScheme = computed(() => deriveBrandScheme(draftColors.value, draftOverrides.value))
  const autoColors = computed(() =>
    Object.fromEntries(
      DERIVED_BRAND_COLORS.map((key) => [
        key,
        deriveBrandScheme(draftColors.value, without(draftOverrides.value, key))?.[key] ?? null,
      ]),
    ),
  )
  return { draftScheme, autoColors, hasValidColors: computed(() => draftScheme.value !== null) }
}

// The unsaved base colours and overrides of the visual-identity screen.
export function useBrandColorDraft() {
  const saved = ref({ colors: { ...DEFAULT_BRAND_COLORS }, overrides: {} })
  const defaultColors = ref({ ...DEFAULT_BRAND_COLORS })
  const draftColors = ref({ ...DEFAULT_BRAND_COLORS })
  const draftOverrides = ref({})

  const isChanged = computed(
    () =>
      !sameColors(draftColors.value, saved.value.colors) ||
      !sameOverrides(draftOverrides.value, saved.value.overrides),
  )
  const isDefault = computed(
    () =>
      sameColors(draftColors.value, defaultColors.value) &&
      Object.keys(draftOverrides.value).length === 0,
  )

  function applySaved(theme) {
    saved.value = { colors: { ...theme.colors }, overrides: { ...theme.overrides } }
    draftColors.value = { ...theme.colors }
    draftOverrides.value = { ...theme.overrides }
    defaultColors.value = { ...(theme.defaultColors ?? DEFAULT_BRAND_COLORS) }
  }

  return {
    draftColors,
    draftOverrides,
    ...useDraftScheme(draftColors, draftOverrides),
    isChanged,
    isDefault,
    setColor: (role, value) => (draftColors.value = { ...draftColors.value, [role]: value }),
    overrideColor: (key, value) =>
      (draftOverrides.value = { ...draftOverrides.value, [key]: value }),
    restoreAutoColor: (key) => (draftOverrides.value = without(draftOverrides.value, key)),
    fillDefaultColors() {
      draftColors.value = { ...defaultColors.value }
      draftOverrides.value = {}
    },
    applySaved,
    payload: () => ({
      primaryColor: normalizeHex(draftColors.value.primary),
      secondaryColor: normalizeHex(draftColors.value.secondary),
      colorOverrides: Object.fromEntries(
        Object.entries(draftOverrides.value).map(([key, value]) => [key, normalizeHex(value)]),
      ),
    }),
  }
}
