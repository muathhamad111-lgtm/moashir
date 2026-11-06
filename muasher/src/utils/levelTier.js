import {
  LEVEL_CERTIFICATE_STATUS,
  LEVEL_MINI_CARD,
  LEVEL_TIERS,
  METAL_TIERS,
  OPEN_ACCREDITED_STATUSES,
  TIER_ORDER,
} from '@/constants/levels'

function issuedTime(certificate) {
  return new Date(certificate.issuedAt ?? 0).getTime()
}

export function currentLevelCertificate(certificates = []) {
  return (
    [...certificates]
      .filter((certificate) => certificate?.status === LEVEL_CERTIFICATE_STATUS)
      .sort((left, right) => issuedTime(right) - issuedTime(left))[0] ?? null
  )
}

export function hasOpenAccredited(current) {
  return OPEN_ACCREDITED_STATUSES.includes(current?.active?.status)
}

export function isMetalTier(tier) {
  return METAL_TIERS.includes(tier)
}

export function tierOf({ certificate = null, accredited = null } = {}) {
  if (certificate && METAL_TIERS.includes(certificate.level)) return certificate.level
  if (certificate) return LEVEL_TIERS.PARTICIPATION
  return hasOpenAccredited(accredited) ? LEVEL_TIERS.PROGRESS : LEVEL_TIERS.NONE
}

export function tierOfLevel(level, hasOpenRequest = false) {
  if (isMetalTier(level) || level === LEVEL_TIERS.PARTICIPATION) return level
  return hasOpenRequest ? LEVEL_TIERS.PROGRESS : LEVEL_TIERS.NONE
}

export function reassessmentOf(tier, hasOpenRequest = false) {
  return isMetalTier(tier) && hasOpenRequest === true
}

export function showsReassessment({ certificate = null, accredited = null } = {}) {
  return isMetalTier(certificate?.level) && hasOpenAccredited(accredited)
}

export function miniCardOf(tier) {
  return isMetalTier(tier) ? LEVEL_MINI_CARD.level : LEVEL_MINI_CARD[tier]
}

export function tierRank(tier) {
  return TIER_ORDER.indexOf(tier)
}

export function tierPalette(tier) {
  return isMetalTier(tier) ? tier : 'neutral'
}
