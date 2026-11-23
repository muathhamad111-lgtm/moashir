import { localizedText } from '@/utils/localizedText'

export const AWARD_CERTIFICATE_KINDS = Object.freeze([
  'advanced_winner',
  'advanced_qualified',
  'advanced_participation',
])

const RANKS = [1, 2, 3]

export function isAwardCertificate(certificate) {
  return AWARD_CERTIFICATE_KINDS.includes(certificate?.kind)
}

export function toAwardFacts(raw) {
  if (!raw) return null
  return {
    cycleName: raw.cycleName ?? null,
    cycleYear: raw.cycleYear ?? null,
    region: raw.region ?? null,
    regionalRank: raw.regionalRank ?? null,
    nationalRank: raw.nationalRank ?? null,
  }
}

function rankText(t, rank) {
  return RANKS.includes(rank) ? t(`certificates.award.ranks.${rank}`) : ''
}

// The rows an award certificate shows: its cycle, then its region and ranks when it has them. Never a score.
export function awardFactRows(award, { t, locale }) {
  if (!award) return []
  const cycle = localizedText(award.cycleName, locale)
  const rows = [
    {
      key: 'cycle',
      label: t('certificates.award.fields.cycle'),
      value: award.cycleYear ? `${cycle} ${award.cycleYear}` : cycle,
    },
    {
      key: 'region',
      label: t('certificates.award.fields.region'),
      value: localizedText(award.region, locale),
    },
    {
      key: 'regionalRank',
      label: t('certificates.award.fields.regionalRank'),
      value: rankText(t, award.regionalRank),
    },
    {
      key: 'nationalRank',
      label: t('certificates.award.fields.nationalRank'),
      value: rankText(t, award.nationalRank),
    },
  ]
  return rows.filter((row) => row.value)
}
