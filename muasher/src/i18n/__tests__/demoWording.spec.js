import { describe, expect, it } from 'vitest'
import ar from '@/i18n/locales/ar.json'
import en from '@/i18n/locales/en.json'

const ARABIC_DEMO_WORDS = /تجريبي|توضيحي|مثال|عينة/
const ENGLISH_DEMO_WORDS = /\b(demo|trial|sample|example)s?\b/i
const OWN_ASSOCIATION = /جمعيتك|جمعيتكم|حساب الجمعية|ملف الجمعية/

const KEPT = [
  { prefix: 'gallery.', reason: 'DW-10 dev-only component gallery' },
  { prefix: 'integrations.', reason: 'DW-9 staff provider test-mode terms' },
  { prefix: 'settings.bankAccounts.example', reason: 'DW-5 example IBAN until the real account' },
  {
    prefix: 'portal.transfer.details.ibanExample',
    reason: 'DW-5 example IBAN until the real account',
  },
]

function leaves(node, path = []) {
  if (typeof node === 'string') return [{ path: path.join('.'), value: node }]
  if (!node || typeof node !== 'object') return []
  return Object.entries(node).flatMap(([key, child]) => leaves(child, [...path, key]))
}

function isKept(path) {
  return KEPT.some(({ prefix }) => path.startsWith(prefix))
}

function hits(messages, pattern) {
  return leaves(messages)
    .filter((entry) => !isKept(entry.path) && pattern.test(entry.value))
    .map((entry) => entry.path)
}

describe('demo-free wording (J-4)', () => {
  it('keeps the Arabic messages free of demo wording outside the kept exceptions', () => {
    expect(hits(ar, ARABIC_DEMO_WORDS)).toEqual([])
  })

  it('keeps the English messages free of demo wording outside the kept exceptions', () => {
    expect(hits(en, ENGLISH_DEMO_WORDS)).toEqual([])
  })

  it('uses the neutral receipt, verification and hero texts in both languages', () => {
    expect(ar.finance.receipt.notProof).toBe('غير معتمد كإثبات دفع')
    expect(en.finance.receipt.notProof).toBe('Not valid as proof of payment')
    expect(ar.verify.results.unverifiable.title).toBe('غير صالحة للتحقق')
    expect(en.verify.results.unverifiable.title).toBe('Not valid for verification')
    expect(ar.public.home.preview.label).toBe('معاينة تقرير النضج')
    expect(en.public.home.preview.label).toBe('Maturity report preview')
  })

  it('never says test to the entity in its payment notices', () => {
    const entityTexts = [
      ...leaves(ar.finance.testMode.entity),
      ...leaves(ar.portal.payments),
      ...leaves(ar.portal.certificates),
    ]
    expect(entityTexts.filter((entry) => /اختبار|تجريبي/.test(entry.value))).toEqual([])
  })
})

describe('one entity term (W-1)', () => {
  it('names the own organisation «جهتك» / «الجهة», never «جمعيتك»', () => {
    expect(hits(ar, OWN_ASSOCIATION)).toEqual([])
    expect(ar.portal.topBar.area).toBe('حساب الجهة')
  })
})
