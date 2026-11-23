import { computed, ref } from 'vue'
import { BRAND_RAIL_ROLES } from '@/constants/branding'
import { autoRailColors } from '@/utils/brandRail'
import { normalizeHex } from '@/utils/brandingContrast'

const AUTO_RAIL = Object.freeze({ top: null, bottom: null })

// The unsaved sidebar gradient colours of the visual-identity screen. A null colour follows the
// brand colours; a chosen colour equal to the automatic one is sent as null, so it keeps following.
export function useRailColorDraft(draftColors) {
  const savedRail = ref({ ...AUTO_RAIL })
  const draftRail = ref({ ...AUTO_RAIL })

  const autoRail = computed(() => {
    const primary = normalizeHex(draftColors.value.primary)
    const secondary = normalizeHex(draftColors.value.secondary)
    return primary && secondary ? autoRailColors({ primary, secondary }) : null
  })
  const shownRail = computed(() =>
    Object.fromEntries(
      BRAND_RAIL_ROLES.map((role) => [role, draftRail.value[role] ?? autoRail.value?.[role] ?? '']),
    ),
  )
  const railValid = computed(() =>
    BRAND_RAIL_ROLES.every(
      (role) => draftRail.value[role] === null || normalizeHex(draftRail.value[role]),
    ),
  )

  function chosen(role) {
    const value = normalizeHex(draftRail.value[role])
    return value && value !== autoRail.value?.[role] ? value : null
  }

  function applySavedRail(rail) {
    savedRail.value = { ...AUTO_RAIL, ...rail }
    draftRail.value = { ...AUTO_RAIL, ...rail }
  }

  return {
    draftRail,
    autoRail,
    shownRail,
    railValid,
    railChanged: computed(() =>
      BRAND_RAIL_ROLES.some(
        (role) => chosen(role) !== (normalizeHex(savedRail.value[role]) ?? null),
      ),
    ),
    railIsAuto: computed(() => BRAND_RAIL_ROLES.every((role) => chosen(role) === null)),
    setRailColor: (role, value) => (draftRail.value = { ...draftRail.value, [role]: value }),
    restoreAutoRail: (role) => (draftRail.value = { ...draftRail.value, [role]: null }),
    useRailPair: ({ top, bottom }) => (draftRail.value = { top, bottom }),
    swapRail: () =>
      (draftRail.value = { top: shownRail.value.bottom, bottom: shownRail.value.top }),
    resetRail: () => (draftRail.value = { ...AUTO_RAIL }),
    applySavedRail,
    railPayload: () => ({ sidebarTopColor: chosen('top'), sidebarBottomColor: chosen('bottom') }),
  }
}
