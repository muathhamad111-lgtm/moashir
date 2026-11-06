import { MockRejection, messageEntry } from '../../contract/rejection'
import { insightsMessages } from '../../fixtures/insightsMessages'
import { mainsOf, versionWithStatus } from '../criteriaStore'
import { forgetState, persistState, restoredState } from './insightsPersistence'

const STORAGE_KEY = 'moashir_mock_maturity_texts'
const BANDS = ['emerging', 'developing', 'mature', 'leading']
const MAX_CHARS = 600
const MARKUP = /<[^>]+>/
const PUBLISHED = 'published'

const GENERIC_TEXTS = {
  emerging: { ar: 'ممارسات أولية أو غير منتظمة.', en: 'Early or irregular practices.' },
  developing: {
    ar: 'ممارسات قائمة تحتاج إلى توثيق أوسع وقياس منتظم.',
    en: 'Existing practices that need wider documentation and regular measurement.',
  },
  mature: {
    ar: 'ممارسات موثقة ومطبقة على نطاق واسع مع قياس منتظم.',
    en: 'Documented practices applied widely, with regular measurement.',
  },
  leading: {
    ar: 'ممارسات متكاملة ومقيسة تُراجع وتُحسَّن باستمرار.',
    en: 'Integrated, measured practices that are reviewed and improved continuously.',
  },
}

function seeded() {
  return {
    nextId: BANDS.length + 1,
    rows: BANDS.map((band, index) => ({
      id: index + 1,
      band,
      mainCriterionCode: null,
      text: { ...GENERIC_TEXTS[band] },
      updatedAt: '2026-11-01T08:00:00Z',
    })),
  }
}

let state = null

function texts() {
  if (!state) state = restoredState(STORAGE_KEY) ?? seeded()
  return state
}

function persist() {
  persistState(STORAGE_KEY, texts())
}

export function resetMaturityTexts() {
  state = seeded()
  forgetState(STORAGE_KEY)
}

function publishedCodes() {
  const version = versionWithStatus(PUBLISHED)
  return new Set(version ? mainsOf(version.id).map((main) => main.code) : [])
}

export function maturityTextRows() {
  const codes = publishedCodes()
  return [...texts().rows]
    .sort(
      (left, right) =>
        Number(Boolean(left.mainCriterionCode)) - Number(Boolean(right.mainCriterionCode)) ||
        left.id - right.id,
    )
    .map((row) => ({
      id: row.id,
      band: row.band,
      mainCriterionCode: row.mainCriterionCode,
      isGeneric: row.mainCriterionCode === null,
      inPublishedVersion: row.mainCriterionCode === null ? null : codes.has(row.mainCriterionCode),
      text: row.text ? { ...row.text } : null,
      updatedAt: row.updatedAt,
    }))
}

export function interpretationOf(band, code) {
  const rows = texts().rows
  const override = rows.find((row) => row.band === band && row.mainCriterionCode === code)
  const generic = rows.find((row) => row.band === band && row.mainCriterionCode === null)
  const text = override?.text ?? generic?.text ?? null
  return text?.ar && text?.en ? { ...text } : null
}

function textError(value) {
  if (typeof value !== 'string' || !value.trim()) return 'insights.text_required'
  if (value.length > MAX_CHARS) return 'insights.text_too_long'
  return MARKUP.test(value) ? 'insights.text_markup' : null
}

function validate(body) {
  const errors = ['ar', 'en']
    .map((locale) => [`text.${locale}`, textError(body.text?.[locale])])
    .filter(([, code]) => code)
  const code = body.mainCriterionCode
  if (code && !publishedCodes().has(code))
    errors.push(['mainCriterionCode', 'insights.code_unknown'])
  if (errors.length)
    throw new MockRejection(422, 'validation_failed', {
      fields: Object.fromEntries(
        errors.map(([field, key]) => [
          field,
          [messageEntry(insightsMessages, key, { max: MAX_CHARS })],
        ]),
      ),
    })
}

export function saveMaturityText(band, body) {
  if (!BANDS.includes(band)) throw new MockRejection(404, 'not_found')
  validate(body)
  const code = body.mainCriterionCode || null
  const rows = texts().rows
  const existing = rows.find((row) => row.band === band && row.mainCriterionCode === code)
  const text = { ar: body.text.ar.trim(), en: body.text.en.trim() }
  const row = existing ?? { id: texts().nextId++, band, mainCriterionCode: code }
  Object.assign(row, { text, updatedAt: new Date().toISOString() })
  if (!existing) rows.push(row)
  persist()
  return maturityTextRows().find((item) => item.id === row.id)
}

export function deleteMaturityText(id) {
  const rows = texts().rows
  const index = rows.findIndex((row) => row.id === Number(id))
  if (index === -1) throw new MockRejection(404, 'not_found')
  if (rows[index].mainCriterionCode === null)
    throw new MockRejection(422, 'insights.generic_text_not_deletable')
  rows.splice(index, 1)
  persist()
}
