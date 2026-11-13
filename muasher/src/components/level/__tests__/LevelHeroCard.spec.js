import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import LevelHeroCard from '@/components/level/LevelHeroCard.vue'
import { LEVEL_MINI_CARD } from '@/constants/levels'
import { ROUTE_NAMES } from '@/constants/routeNames'

const RANGES = [
  { level: 'bronze', min: 60, max: 69.99 },
  { level: 'silver', min: 70, max: 79.99 },
  { level: 'gold', min: 80, max: 89.99 },
  { level: 'diamond', min: 90, max: null },
]

const SILVER = {
  id: 'c-silver',
  number: 'CRT-2026-000003',
  level: 'silver',
  status: 'issued',
  percent: '74.50',
  issuedAt: '2026-09-05T08:00:00Z',
  validUntil: '2027-09-04',
  validity: 'valid',
  daysUntilExpiry: 296,
}

const NEXT_LEVEL = { name: ROUTE_NAMES.PORTAL_TOOL_STRENGTHS }

function render(props = {}, locale = 'ar') {
  return mount(LevelHeroCard, {
    props: {
      tier: 'silver',
      certificate: SILVER,
      ranges: RANGES,
      action: LEVEL_MINI_CARD.level,
      nextLevelTo: NEXT_LEVEL,
      playKey: 'spec.c-silver',
      ...props,
    },
    global: createTestGlobals({ locale }),
  })
}

describe('LevelHeroCard — Option A identity and progress', () => {
  it('shows the level, one validity chip, the certificate line and one primary action', () => {
    const wrapper = render({ reassessment: true })
    expect(wrapper.find('.level-hero__name').text()).toBe('المستوى الفضي')
    expect(wrapper.find('[data-testid="level-validity"]').text()).toBe('سارية · حتى 2027-09-04')
    expect(wrapper.find('[data-testid="level-reassessment"]').text()).toBe('قيد إعادة التقييم')
    expect(wrapper.find('[data-testid="level-meta"]').text()).toBe(
      'رقم الشهادة CRT-2026-000003 · صدرت 2026-09-05',
    )
    const action = wrapper.find('[data-testid="level-hero-action"]')
    expect(action.element.tagName).toBe('A')
    expect(action.classes()).toEqual(expect.arrayContaining(['btn', 'btn--primary']))
    expect(action.text()).toBe('عرض الشهادة')
  })

  it('shows the score once with its final value as the accessible text', () => {
    const wrapper = render()
    expect(wrapper.findAll('.level-hero__number')).toHaveLength(1)
    expect(wrapper.find('.level-hero__number').text()).toBe('74.5')
    expect(wrapper.find('.level-hero__figure-value').attributes('aria-hidden')).toBe('true')
    expect(wrapper.find('.level-hero__figure .level-hero__sr').text()).toBe('الدرجة المعتمدة 74.5%')
    expect(wrapper.find('.level-hero__remaining').text()).toBe('تبقّى 5.5% لبلوغ المستوى الذهبي')
    expect(wrapper.find('.level-hero__journey-title').text()).toBe('رحلة التميّز')
    expect(wrapper.find('[data-testid="hero-next-level"]').exists()).toBe(true)
    expect(wrapper.find('.level-ring').exists()).toBe(false)
  })

  it('labels the section by the level name', () => {
    const wrapper = render()
    const id = wrapper.find('.level-hero__name').attributes('id')
    expect(wrapper.attributes('aria-labelledby')).toBe(id)
  })

  it('reads the top level without a remaining zone', () => {
    const wrapper = render({
      tier: 'diamond',
      certificate: { ...SILVER, level: 'diamond', percent: '93.10' },
      nextLevelTo: null,
    })
    expect(wrapper.find('.level-hero__remaining').text()).toBe('بلغت جهتك أعلى مستوى في النموذج')
    expect(wrapper.find('.level-track__remaining').exists()).toBe(false)
  })

  it('shows the no-level state with the start line and without a score', () => {
    const wrapper = render({
      tier: 'none',
      certificate: null,
      action: LEVEL_MINI_CARD.none,
      nextLevelTo: null,
    })
    expect(wrapper.find('.level-hero__name').text()).toBe('لم تحصل جهتك على مستوى بعد')
    expect(wrapper.find('.level-hero__figure').exists()).toBe(false)
    expect(wrapper.find('[data-testid="level-validity"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="level-meta"]').exists()).toBe(false)
    expect(wrapper.find('.level-hero__remaining').text()).toBe('تبدأ المستويات من 60%')
    expect(wrapper.find('[data-testid="level-hero-action"]').text()).toBe('ابدأ التقييم المعتمد')
  })

  it('shows the in-progress state with the follow action', () => {
    const wrapper = render({
      tier: 'progress',
      certificate: null,
      action: LEVEL_MINI_CARD.progress,
      nextLevelTo: null,
    })
    expect(wrapper.find('.level-hero__name').text()).toBe('التقييم المعتمد قيد الإجراء')
    expect(wrapper.find('[data-testid="level-hero-action"]').text()).toBe('تابع طلبك')
  })

  it('shows the participation certificate with the remaining stretch to bronze', () => {
    const wrapper = render({
      tier: 'participation',
      certificate: { ...SILVER, level: null, percent: '54.00' },
      action: LEVEL_MINI_CARD.participation,
    })
    expect(wrapper.find('.level-hero__name').text()).toBe('شهادة مشاركة')
    expect(wrapper.find('.level-hero__remaining').text()).toBe('تبقّى 6% لبلوغ المستوى البرونزي')
    expect(wrapper.find('[data-testid="level-hero-action"]').text()).toBe('عرض شهادة المشاركة')
  })

  it('words the expiring-soon chip with the days left, not by colour only', () => {
    const wrapper = render({
      certificate: {
        ...SILVER,
        validUntil: '2026-11-30',
        validity: 'expiring_soon',
        daysUntilExpiry: 18,
      },
      action: LEVEL_MINI_CARD.none,
    })
    const chip = wrapper.find('[data-testid="level-validity"]')
    expect(chip.text()).toBe('تنتهي خلال 18 يومًا · 2026-11-30')
    expect(chip.classes()).toContain('level-hero__chip--expiring')
    expect(wrapper.attributes('data-validity')).toBe('expiring_soon')
    expect(wrapper.find('[data-testid="hero-next-level"]').exists()).toBe(true)
  })

  it.each([
    [0, 'تنتهي اليوم · 2026-11-30', 'Expires today · 2026-11-30'],
    [1, 'تنتهي خلال يوم واحد · 2026-11-30', 'Expires in 1 day · 2026-11-30'],
    [2, 'تنتهي خلال يومين · 2026-11-30', 'Expires in 2 days · 2026-11-30'],
    [5, 'تنتهي خلال 5 أيام · 2026-11-30', 'Expires in 5 days · 2026-11-30'],
  ])('pluralises the days left (%i) in both languages', (days, ar, en) => {
    const certificate = {
      ...SILVER,
      validUntil: '2026-11-30',
      validity: 'expiring_soon',
      daysUntilExpiry: days,
    }
    expect(render({ certificate }).find('[data-testid="level-validity"]').text()).toBe(ar)
    expect(render({ certificate }, 'en').find('[data-testid="level-validity"]').text()).toBe(en)
  })

  it('mutes an expired level, words it «منتهية» and hides the next-level link', () => {
    const wrapper = render({
      certificate: {
        ...SILVER,
        validUntil: '2026-10-31',
        validity: 'expired',
        daysUntilExpiry: null,
      },
      action: LEVEL_MINI_CARD.none,
    })
    expect(wrapper.classes()).toContain('level-hero--expired')
    expect(wrapper.find('.level-hero__name').text()).toBe('المستوى الفضي')
    expect(wrapper.find('[data-testid="level-validity"]').text()).toBe('منتهية · 2026-10-31')
    expect(wrapper.find('[data-testid="hero-next-level"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="level-hero-action"]').text()).toBe('ابدأ التقييم المعتمد')
  })

  it('keeps the skeleton busy and labelled while the level loads', () => {
    const wrapper = render({ loading: true })
    expect(wrapper.attributes('aria-busy')).toBe('true')
    expect(wrapper.find('[data-testid="level-hero-skeleton"]').text()).toBe('جارٍ تحميل مستوى جهتك')
    expect(wrapper.find('.level-hero__name').exists()).toBe(false)
  })

  it('reports a load failure and retries on request', async () => {
    const wrapper = render({ failed: true })
    expect(wrapper.find('[role="alert"]').text()).toContain('تعذّر تحميل مستوى جهتك.')
    await wrapper.find('.level-hero__error button').trigger('click')
    expect(wrapper.emitted('retry')).toHaveLength(1)
  })

  it('reads in English with "Your entity" wording', () => {
    const wrapper = render(
      { tier: 'none', certificate: null, action: LEVEL_MINI_CARD.none, nextLevelTo: null },
      'en',
    )
    expect(wrapper.find('.level-hero__name').text()).toBe('Your entity has no level yet')
    expect(wrapper.find('.level-hero__remaining').text()).toBe('Levels start at 60%')
    const silver = render({}, 'en')
    expect(silver.find('[data-testid="level-validity"]').text()).toBe('Valid · until 2027-09-04')
    expect(silver.find('[data-testid="level-meta"]').text()).toBe(
      'Certificate CRT-2026-000003 · issued 2026-09-05',
    )
    expect(silver.find('.level-hero__remaining').text()).toBe('5.5% to Gold level')
  })
})
