import { describe, expect, it } from 'vitest'
import { readdirSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import process from 'node:process'
import ar from '@/i18n/locales/ar.json'
import en from '@/i18n/locales/en.json'
import devAr from '@/i18n/locales/dev/ar.json'
import devEn from '@/i18n/locales/dev/en.json'
import { forbiddenTermsIn } from '@/i18n/forbiddenTerms'

const FIXTURES_DIR = `${resolve(process.cwd(), 'src/mocks/fixtures')}/`
const BILINGUAL_LITERAL = /\b(?:ar|en): '((?:[^'\\]|\\.)*)'/g

function leaves(node, path = []) {
  if (typeof node === 'string') return [{ path: path.join('.'), value: node }]
  if (!node || typeof node !== 'object') return []
  return Object.entries(node).flatMap(([key, child]) => leaves(child, [...path, key]))
}

function violations(entries) {
  return entries
    .map((entry) => ({ ...entry, terms: forbiddenTermsIn(entry.value) }))
    .filter((entry) => entry.terms.length)
    .map((entry) => `${entry.path}: ${entry.terms.join(', ')}`)
}

function fixtureStrings() {
  return readdirSync(FIXTURES_DIR)
    .filter((file) => file.endsWith('.js'))
    .flatMap((file) =>
      [...readFileSync(`${FIXTURES_DIR}${file}`, 'utf8').matchAll(BILINGUAL_LITERAL)].map(
        (match) => ({
          path: file,
          value: match[1],
        }),
      ),
    )
}

describe('forbidden terminology (04-guardrails Content)', () => {
  it.each([
    ['ar', ar],
    ['en', en],
    ['dev/ar', devAr],
    ['dev/en', devEn],
  ])('keeps the %s messages free of forbidden words', (name, messages) => {
    expect(violations(leaves(messages))).toEqual([])
  })

  it('keeps the bilingual mock content free of forbidden words', () => {
    expect(violations(fixtureStrings())).toEqual([])
  })

  it('names the Kit consistently in both languages', () => {
    expect(en.nav.bag).toBe('Quick Excellence Kit')
    expect(ar.nav.bag).toBe('حقيبة التميز السريع')
    const arabicKitMentions = leaves(ar).filter((entry) =>
      /حقيبة التميز(?! السريع)/.test(entry.value),
    )
    expect(arabicKitMentions).toEqual([])
  })

  it.each([
    ['Assign a Judge', ['Judge']],
    ['Jury average', ['Jury']],
    ['Two jurors', ['Juror']],
    ['Download invoice', ['invoice']],
    ['Senior consultant', ['Consultant']],
    ['Explore the Excellence Bag', ['Excellence Bag']],
    ['لجنة التحكيم', ['التحكيم']],
    ['اسم المحكّم', ['محكم']],
    ['رقم الفاتورة', ['فاتورة']],
    ['Quick Excellence Kit', []],
    ['Assessors committee', []],
    ['Payment receipt', []],
  ])('detects forbidden words in "%s"', (text, expected) => {
    expect(forbiddenTermsIn(text)).toEqual(expected)
  })
})
