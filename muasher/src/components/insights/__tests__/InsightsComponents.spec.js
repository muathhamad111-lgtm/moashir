import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import GapColumn from '@/components/insights/GapColumn.vue'
import MaturityBandChip from '@/components/insights/MaturityBandChip.vue'
import InsightsEmptyState from '@/components/insights/InsightsEmptyState.vue'
import InsightsNotices from '@/components/insights/InsightsNotices.vue'
import StrengthsGapsSummary from '@/components/insights/StrengthsGapsSummary.vue'
import ExecutiveFacts from '@/components/insights/ExecutiveFacts.vue'
import MaturityCriterionCard from '@/components/insights/MaturityCriterionCard.vue'
import InsightsRulesLine from '@/components/insights/InsightsRulesLine.vue'

const RULES = { criticalBelow: '50.00', strengthFrom: '80.00', version: 2 }

function item(index, overrides = {}) {
  return {
    mainCode: 'M1',
    mainName: { ar: 'القيادة', en: 'Leadership' },
    subCode: `M1.${index}`,
    subName: { ar: `معيار ${index}`, en: `Criterion ${index}` },
    weight: '10.00',
    earned: '9.50',
    percent: '95.00',
    lostPoints: '0.50',
    class: 'strength',
    assessor: { strengths: null, improvementPoints: null },
    ...overrides,
  }
}

function render(component, props, locale = 'ar') {
  return mount(component, { props, global: createTestGlobals({ locale }) })
}

describe('excellence tools components', () => {
  it('previews six items per column and expands the rest', async () => {
    const items = Array.from({ length: 9 }, (_, index) => item(index + 1))
    const wrapper = render(GapColumn, { id: 'col', gapClass: 'strength', items, rules: RULES })
    expect(wrapper.findAll('[data-testid="gap-item"]')).toHaveLength(6)
    const more = wrapper.find('.gap-column__more')
    expect(more.text()).toContain('3')
    await more.trigger('click')
    expect(wrapper.findAll('[data-testid="gap-item"]')).toHaveLength(9)
    expect(more.attributes('aria-expanded')).toBe('true')
  })

  it('quotes the assessor texts or says there is none', () => {
    const quoted = item(1, {
      assessor: { strengths: 'خطة معتمدة.', improvementPoints: 'قياس ربع سنوي.' },
    })
    const wrapper = render(GapColumn, {
      id: 'col',
      gapClass: 'strength',
      items: [quoted, item(2)],
      rules: RULES,
    })
    const cards = wrapper.findAll('[data-testid="gap-item"]')
    expect(cards[0].findAll('.assessor-quote')).toHaveLength(2)
    expect(cards[0].find('.assessor-quote__text').text()).toBe('خطة معتمدة.')
    expect(cards[1].find('.gap-item__none').text()).toBe(
      'لا توجد ملاحظات من المقيّم على هذا المعيار',
    )
  })

  it('shows the empty column text', () => {
    const wrapper = render(GapColumn, {
      id: 'c',
      gapClass: 'critical_gap',
      items: [],
      rules: RULES,
    })
    expect(wrapper.find('.gap-column__empty').text()).toBe('لا توجد فجوات حرجة في هذه النتيجة')
  })

  it('fills the band glyph by stage and renders «نامٍ» with its tanween', () => {
    const wrapper = render(MaturityBandChip, { band: 'developing' })
    expect(wrapper.findAll('.band-chip__bar.is-filled')).toHaveLength(2)
    expect(wrapper.text()).toContain('نامٍ')
  })

  it('isolates percentages in the rules line', () => {
    const wrapper = render(InsightsRulesLine, { kind: 'gaps', rules: RULES })
    const isolated = wrapper.findAll('bdi[dir="ltr"]').map((node) => node.text())
    expect(isolated).toEqual(['50%', '80%', '2'])
  })

  it('adds the totals tile from the class counts', () => {
    const wrapper = render(StrengthsGapsSummary, {
      counts: { strength: 20, opportunity: 6, criticalGap: 2 },
      mains: [{ code: 'M1', name: { ar: 'القيادة' }, percent: '88.00', class: 'strength' }],
    })
    expect(wrapper.find('[data-testid="sg-total"] .sg-summary__number').text()).toBe('28')
  })

  it.each([
    [
      'in_progress',
      { kind: 'view_request', assessmentId: 9105 },
      '/assessment/accredited/form/9105',
    ],
    ['only_self', { kind: 'start_accredited', assessmentId: null }, '/assessment/accredited'],
  ])('links the %s empty state to its action', (reason, cta, href) => {
    const wrapper = render(InsightsEmptyState, { emptyState: { reason, cta } })
    expect(wrapper.find('.btn--primary').attributes('href')).toBe(href)
  })

  it('offers no action to staff and to the under-review state', () => {
    const wrapper = render(InsightsEmptyState, {
      emptyState: { reason: 'under_review', cta: null },
    })
    expect(wrapper.find('.btn').exists()).toBe(false)
    expect(wrapper.text()).toContain('نتيجتك قيد المراجعة')
  })

  it('names the excluded result and the fallback result', () => {
    const wrapper = render(InsightsNotices, {
      overview: {
        selected: { number: 'ACC-2026-000014' },
        exclusions: [
          { assessmentId: 71, number: 'ACC-2026-000071', reason: 'certificate_revoked' },
        ],
        inProgressRequest: null,
      },
    })
    const notice = wrapper.find('[data-testid="insights-exclusion"]')
    expect(notice.text()).toContain('ACC-2026-000071')
    expect(notice.text()).toContain('لأن شهادتها ملغاة')
    expect(notice.text()).toContain('ACC-2026-000014')
  })

  it.each([
    [{ standing: 'level', level: 'gold' }, 'ذهبي'],
    [{ standing: 'participation', level: null }, 'شهادة مشاركة'],
    [{ standing: 'not_issued', level: null }, 'لم تُصدر شهادة'],
  ])('shows the level only with a valid certificate', (certificate, label) => {
    const wrapper = render(ExecutiveFacts, {
      summary: {
        score: { percent: '82.00', earned: '229.60', totalWeight: '280.00' },
        certificate,
        change: { deltaPp: null, previous: null },
      },
    })
    expect(wrapper.find('[data-testid="executive-level"]').text()).toContain(label)
  })

  it('marks a new criterion and expands its sub-criteria', async () => {
    const wrapper = render(MaturityCriterionCard, {
      main: {
        code: 'M3',
        name: { ar: 'الموارد البشرية' },
        description: { ar: 'تخطيط العاملين' },
        weight: '30.00',
        earned: '23.70',
        percent: '79.00',
        band: 'mature',
        interpretation: { ar: 'ممارسات موثقة.' },
        comparison: 'new_in_version',
        deltaPp: null,
        previousBand: null,
        subs: [
          { code: 'M3.1', name: { ar: 'تخطيط' }, percent: '86.00', band: 'leading', deltaPp: null },
        ],
      },
    })
    expect(wrapper.find('.maturity-card__delta').text()).toBe('معيار جديد')
    expect(wrapper.find('.maturity-card__subs').isVisible()).toBe(false)
    await wrapper.find('.maturity-card__toggle').trigger('click')
    expect(wrapper.find('.maturity-card__toggle').attributes('aria-expanded')).toBe('true')
  })
})
