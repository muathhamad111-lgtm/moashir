import { useI18n } from 'vue-i18n'
import { toCamelKey } from '@/api/caseMapping'
import { useCriteria } from '@/composables/useCriteria'
import { useScoringTypeOptions } from '@/composables/useScoringTypeOptions'
import { EVALUATION_TYPES } from '@/constants/criteria'

const SCORING_FIELDS = Object.freeze([
  'scoring_type',
  'default_scoring_type',
  'default_point_scoring_type',
])
const YES_NO_FIELDS = Object.freeze(['active', 'has_cv', 'distribute_equally'])
const EMPTY = '—'

const isText = (value) =>
  value !== null &&
  typeof value === 'object' &&
  !Array.isArray(value) &&
  ('ar' in value || 'en' in value)

const textIn = (value, language) => value?.[language] || EMPTY

// A changed field as a row «field: before → after», with each value in words.
export function useCriteriaChangeFormat() {
  const { t, locale } = useI18n()
  const { localized, weight } = useCriteria()
  const { labelOf } = useScoringTypeOptions()

  const formatters = {
    weight: (value) => weight(value),
    group: (value) => t(`criteria.groups.${value}`),
    evaluation_type: (value) =>
      t(
        `criteria.evaluationType.${value === EVALUATION_TYPES.DIRECT ? 'direct' : 'guidingPoints'}`,
      ),
    attachments: (value) =>
      t('criteria.review.attachmentCount', { count: value.length }, value.length),
  }

  function format(field, value) {
    if (value === null || value === undefined || value === '') return EMPTY
    if (formatters[field]) return formatters[field](value)
    if (SCORING_FIELDS.includes(field)) return labelOf(value)
    if (YES_NO_FIELDS.includes(field)) return t(`criteria.workspace.sheet.${value ? 'yes' : 'no'}`)
    if (typeof value === 'object') return localized(value) || EMPTY
    return String(value)
  }

  // A bilingual text shows the language that changed: an English-only edit reads as English in Arabic.
  function changedLanguage(before, after) {
    const current = locale.value
    if (textIn(before, current) !== textIn(after, current)) return current
    return current === 'ar' ? 'en' : 'ar'
  }

  function textPair(before, after) {
    const language = changedLanguage(before, after)
    return {
      before: textIn(before, language),
      after: textIn(after, language),
      dir: language === 'ar' ? 'rtl' : 'ltr',
    }
  }

  function valuePair(field, before, after) {
    if (isText(before) || isText(after)) return textPair(before, after)
    return { before: format(field, before), after: format(field, after), dir: undefined }
  }

  // The API sends the field names as values (snake_case) and the before/after maps with camelCase keys.
  function fieldRows(change) {
    return change.fields.map((field) => ({
      field,
      label: t(`criteria.publish.preview.fields.${field}`),
      ...valuePair(field, change.before?.[toCamelKey(field)], change.after?.[toCamelKey(field)]),
    }))
  }

  return { fieldRows }
}
