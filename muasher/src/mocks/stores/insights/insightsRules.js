import { MockRejection, messageEntry } from '../../contract/rejection'
import { insightsMessages } from '../../fixtures/insightsMessages'
import { forgetState, persistState, restoredState } from './insightsPersistence'

const STORAGE_KEY = 'moashir_mock_insights_rules'
const RULE_PATTERN = /^\d{1,3}(\.\d{1,2})?$/
const MAX = 100
const GROUPS = {
  gapThresholds: ['criticalBelow', 'strengthFrom'],
  maturityBands: ['developingFrom', 'matureFrom', 'leadingFrom'],
}

function seeded() {
  return {
    gapThresholds: { criticalBelow: '50.00', strengthFrom: '80.00', version: 1 },
    maturityBands: {
      developingFrom: '50.00',
      matureFrom: '70.00',
      leadingFrom: '80.00',
      version: 1,
    },
    effectiveSince: null,
  }
}

let state = null

export function insightsRules() {
  if (!state) state = restoredState(STORAGE_KEY) ?? seeded()
  return state
}

export function resetInsightsRules() {
  state = seeded()
  forgetState(STORAGE_KEY)
}

function fixed(value) {
  return Number(value).toFixed(2)
}

function sameValue(a, b) {
  return fixed(a) === fixed(b)
}

export function insightsRulesResource() {
  const rules = insightsRules()
  return {
    gapThresholds: { ...rules.gapThresholds },
    maturityBands: { ...rules.maturityBands },
    aligned:
      sameValue(rules.gapThresholds.criticalBelow, rules.maturityBands.developingFrom) &&
      sameValue(rules.gapThresholds.strengthFrom, rules.maturityBands.leadingFrom),
    effectiveSince: rules.effectiveSince,
  }
}

export function rulesVersions() {
  const rules = insightsRules()
  return {
    gapThresholdsVersion: rules.gapThresholds.version,
    maturityBandsVersion: rules.maturityBands.version,
    effectiveSince: rules.effectiveSince,
  }
}

function isRule(value) {
  return (
    typeof value === 'string' &&
    RULE_PATTERN.test(value) &&
    Number(value) > 0 &&
    Number(value) <= MAX
  )
}

function groupError(group, values) {
  const keys = GROUPS[group]
  if (keys.some((key) => !isRule(values?.[key]))) return 'insights.rules_out_of_range'
  const numbers = keys.map((key) => Number(values[key]))
  return numbers.every((value, index) => index === 0 || numbers[index - 1] < value)
    ? null
    : 'insights.rules_not_ascending'
}

function validate(body) {
  const errors = Object.keys(GROUPS)
    .filter((group) => Object.hasOwn(body, group))
    .map((group) => [group, groupError(group, body[group])])
    .filter(([, code]) => code)
  if (errors.length)
    throw new MockRejection(422, 'validation_failed', {
      fields: Object.fromEntries(
        errors.map(([group, code]) => [group, [messageEntry(insightsMessages, code)]]),
      ),
    })
}

function changed(current, values, keys) {
  return keys.some((key) => !sameValue(current[key], values[key]))
}

export function updateInsightsRules(user, body) {
  validate(body)
  if (user.superAdmin && !String(body.reason ?? '').trim())
    throw new MockRejection(422, 'reason_required')
  const rules = insightsRules()
  Object.entries(GROUPS)
    .filter(([group, keys]) => body[group] && changed(rules[group], body[group], keys))
    .forEach(([group, keys]) => {
      const next = Object.fromEntries(keys.map((key) => [key, fixed(body[group][key])]))
      rules[group] = { ...next, version: rules[group].version + 1 }
      rules.effectiveSince = new Date().toISOString()
    })
  persistState(STORAGE_KEY, rules)
  return rules
}

export function gapClassOf(percent) {
  const { criticalBelow, strengthFrom } = insightsRules().gapThresholds
  const value = Number(percent)
  if (value < Number(criticalBelow)) return 'critical_gap'
  return value < Number(strengthFrom) ? 'opportunity' : 'strength'
}

export function bandOf(percent) {
  const { developingFrom, matureFrom, leadingFrom } = insightsRules().maturityBands
  const value = Number(percent)
  if (value >= Number(leadingFrom)) return 'leading'
  if (value >= Number(matureFrom)) return 'mature'
  return value >= Number(developingFrom) ? 'developing' : 'emerging'
}
