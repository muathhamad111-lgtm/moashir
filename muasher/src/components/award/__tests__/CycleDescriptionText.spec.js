import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CycleDescriptionText from '@/components/award/CycleDescriptionText.vue'
import { createTestGlobals } from '@/test-utils'

function render(cycle, locale = 'ar') {
  return mount(CycleDescriptionText, {
    props: { cycle },
    global: { plugins: createTestGlobals({ locale }).plugins },
  })
}

const CYCLE = {
  name: { ar: 'دورة الجائزة', en: 'Award cycle' },
  description: {
    ar: 'الفقرة الأولى.\n\n<b>الفقرة الثانية</b>',
    en: 'First paragraph.\n\nSecond paragraph.',
  },
}

describe('CycleDescriptionText (R-5)', () => {
  it('shows the cycle description in paragraphs as plain text in the active language', () => {
    const arabic = render(CYCLE)
    const paragraphs = arabic.findAll('.cycle-description__text')
    expect(paragraphs.map((paragraph) => paragraph.text())).toEqual([
      'الفقرة الأولى.',
      '<b>الفقرة الثانية</b>',
    ])
    expect(arabic.find('b').exists()).toBe(false)
    expect(arabic.find('.cycle-description__title').text()).toBe('دورة الجائزة')
    expect(render(CYCLE, 'en').findAll('.cycle-description__text')).toHaveLength(2)
  })

  it('renders nothing without a description or a cycle', () => {
    expect(
      render({ ...CYCLE, description: null })
        .find('.cycle-description')
        .exists(),
    ).toBe(false)
    expect(render(null).find('.cycle-description').exists()).toBe(false)
  })
})
