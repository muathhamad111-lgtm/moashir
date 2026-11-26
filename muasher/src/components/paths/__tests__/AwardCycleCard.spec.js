import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AwardCycleCard from '@/components/paths/AwardCycleCard.vue'
import { createTestGlobals } from '@/test-utils'

const AWARD = {
  windowState: 'open',
  nextCycle: null,
  cycle: {
    name: { ar: 'دورة الجائزة 2026', en: 'Award cycle 2026' },
    submissionStart: '2026-11-01',
    submissionEnd: '2026-12-01',
    minEntityAgeYears: null,
    regions: [
      { name: { ar: 'الرياض', en: 'Riyadh' } },
      { name: { ar: 'مكة المكرمة', en: 'Makkah' } },
    ],
  },
}

function regionsNote(locale) {
  const wrapper = mount(AwardCycleCard, {
    props: { award: AWARD },
    global: createTestGlobals({ locale }),
  })
  return wrapper.findAll('.path-header-card__note')[0].text()
}

describe('AwardCycleCard', () => {
  it('joins the regions with the English comma in English', () => {
    expect(regionsNote('en')).toContain('Riyadh, Makkah')
    expect(regionsNote('en')).not.toContain('،')
  })

  it('joins the regions with the Arabic comma in Arabic', () => {
    expect(regionsNote('ar')).toContain('الرياض، مكة المكرمة')
  })
})
