import { object as yupObject, string as yupString } from 'yup'
import { toWesternDigits } from '@/utils/numerals'
import { isRuleValue, normalizedRule } from '@/utils/insights'

export const RULE_GROUPS = Object.freeze({
  gapThresholds: ['criticalBelow', 'strengthFrom'],
  maturityBands: ['developingFrom', 'matureFrom', 'leadingFrom'],
})

const ORDER = Object.freeze({
  strengthFrom: 'criticalBelow',
  matureFrom: 'developingFrom',
  leadingFrom: 'matureFrom',
})

export function formValuesOf(settings) {
  return Object.fromEntries(
    Object.entries(RULE_GROUPS).flatMap(([group, keys]) =>
      keys.map((key) => [key, settings?.[group]?.[key] ?? '']),
    ),
  )
}

function ruleField(t, key) {
  const base = yupString()
    .transform((value) => toWesternDigits(value))
    .required(t('validation.required'))
    .test('rule', t('settings.insights.validation.rule'), (value) => isRuleValue(value))
  const lower = ORDER[key]
  if (!lower) return base
  return base.test(
    'ascending',
    t('settings.insights.validation.ascending'),
    function isAbove(value) {
      const floor = this.parent[lower]
      return (
        !isRuleValue(floor) || !isRuleValue(value) || Number(value) > Number(toWesternDigits(floor))
      )
    },
  )
}

export function insightsSettingsSchema(t) {
  const keys = Object.values(RULE_GROUPS).flat()
  return yupObject(Object.fromEntries(keys.map((key) => [key, ruleField(t, key)])))
}

function groupChanged(settings, values, keys, group) {
  return keys.some((key) => normalizedRule(values[key]) !== normalizedRule(settings[group][key]))
}

export function changedRules(settings, values) {
  return Object.fromEntries(
    Object.entries(RULE_GROUPS)
      .filter(([group, keys]) => groupChanged(settings, values, keys, group))
      .map(([group, keys]) => [
        group,
        Object.fromEntries(keys.map((key) => [key, normalizedRule(values[key])])),
      ]),
  )
}

export function fieldErrorsOf(fieldErrors) {
  return Object.fromEntries(
    Object.entries(fieldErrors ?? {})
      .filter(([group]) => RULE_GROUPS[group])
      .map(([group, messages]) => [
        RULE_GROUPS[group].at(-1),
        Array.isArray(messages) ? messages[0] : messages,
      ]),
  )
}
