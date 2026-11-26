import sealParticipation from '@/assets/landing/seals/seal-participation.webp'
import sealBronze from '@/assets/landing/seals/seal-bronze.webp'
import sealSilver from '@/assets/landing/seals/seal-silver.webp'
import sealGold from '@/assets/landing/seals/seal-gold.webp'
import sealDiamond from '@/assets/landing/seals/seal-diamond.webp'

export const PARTICIPATION = 'participation'
export const FULL_SCORE = 100

export const TIER_STYLES = Object.freeze({
  participation: {
    seal: sealParticipation,
    ink: 'var(--lp-accent)',
    glow: 'rgba(152, 216, 211, 0.45)',
    color: 'var(--lp-aqua)',
  },
  bronze: {
    seal: sealBronze,
    ink: 'var(--lp-bronze)',
    glow: 'rgba(158, 108, 53, 0.5)',
    color: 'var(--lp-bronze)',
  },
  silver: {
    seal: sealSilver,
    ink: 'var(--lp-ink-2)',
    glow: 'rgba(192, 192, 192, 0.6)',
    color: 'var(--lp-silver)',
  },
  gold: {
    seal: sealGold,
    ink: 'var(--lp-gold-ink)',
    glow: 'rgba(211, 175, 55, 0.5)',
    color: 'var(--lp-gold)',
  },
  diamond: {
    seal: sealDiamond,
    ink: 'var(--lp-accent)',
    glow: 'rgba(41, 145, 145, 0.6)',
    color: 'var(--lp-diamond)',
  },
})

function styleOf(key) {
  return TIER_STYLES[key] ?? TIER_STYLES[PARTICIPATION]
}

export function landingTiers(ranges) {
  const levels = (ranges ?? []).filter((range) => TIER_STYLES[range.level])
  if (!levels.length) return []
  const participation = { key: PARTICIPATION, label: null, min: 0, max: levels[0].min }
  const ladder = levels.map((range, index) => ({
    key: range.level,
    label: range.label,
    min: range.min,
    max: levels[index + 1]?.min ?? null,
  }))
  return [participation, ...ladder].map((tier) => ({ ...tier, ...styleOf(tier.key) }))
}

export function tierOfScore(tiers, score) {
  return [...tiers].reverse().find((tier) => score >= tier.min) ?? tiers[0] ?? null
}

export function tierSample(tier) {
  const upper = tier.max ?? FULL_SCORE
  return Math.round((tier.min + upper) / 2)
}
