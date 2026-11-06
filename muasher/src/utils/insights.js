import { BAND_BOUNDS, MATURITY_BANDS, RULE_DECIMAL_PATTERN, RULE_MAX } from '@/constants/insights'
import { toWesternDigits } from './numerals'
import { roundToOneDecimal } from './percentText'

const HUNDREDTHS = 100

export function toHundredths(value) {
  return Math.round(Number(value) * HUNDREDTHS)
}

export function deltaValue(deltaPp) {
  if (deltaPp === null || deltaPp === undefined || deltaPp === '') return null
  const numeric = Number(deltaPp)
  return Number.isNaN(numeric) ? null : roundToOneDecimal(numeric)
}

export function bandLevel(band) {
  return MATURITY_BANDS.indexOf(band) + 1
}

export function versionLabelOf(criteriaVersion, locale) {
  if (!criteriaVersion) return ''
  const label = criteriaVersion.label?.[locale] || criteriaVersion.label?.ar
  return label || String(criteriaVersion.versionNumber ?? criteriaVersion.id ?? '')
}

export function itemKey(item) {
  return `${item.mainCode}:${item.subCode}`
}

export function previewOf(items, expanded, count) {
  return expanded ? items : items.slice(0, count)
}

export function bandRanges(rules) {
  const bounds = [0, ...Object.values(BAND_BOUNDS).map((key) => Number(rules[key])), RULE_MAX]
  return MATURITY_BANDS.map((band, index) => ({
    band,
    from: bounds[index],
    to: bounds[index + 1],
  }))
}

export function gapRanges(rules) {
  const critical = Number(rules.criticalBelow)
  const strength = Number(rules.strengthFrom)
  return [
    { gapClass: 'critical_gap', from: 0, to: critical },
    { gapClass: 'opportunity', from: critical, to: strength },
    { gapClass: 'strength', from: strength, to: RULE_MAX },
  ]
}

export function isAligned(gaps, bands) {
  return (
    toHundredths(gaps.criticalBelow) === toHundredths(bands.developingFrom) &&
    toHundredths(gaps.strengthFrom) === toHundredths(bands.leadingFrom)
  )
}

export function normalizedRule(value) {
  const text = toWesternDigits(value)
  if (!RULE_DECIMAL_PATTERN.test(text)) return text
  return (toHundredths(text) / HUNDREDTHS).toFixed(2)
}

export function isRuleValue(value) {
  const text = toWesternDigits(value)
  return RULE_DECIMAL_PATTERN.test(text) && Number(text) > 0 && Number(text) <= RULE_MAX
}

export function isAscending(values) {
  return values.every((value, index) => index === 0 || Number(values[index - 1]) < Number(value))
}

export function vatNumberInput(value) {
  return toWesternDigits(value).replace(/\s/g, '')
}
