import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import CommitteeSeatsList from '@/components/account/CommitteeSeatsList.vue'

const SEAT = {
  id: 1,
  awardCycleId: 3,
  cycleName: { ar: 'دورة جائزة مؤشر', en: 'Moashir Award cycle' },
  status: 'active',
  isSubstitute: false,
  committeeStart: '2026-11-08T09:00:00Z',
  committeeEnd: '2026-12-08T09:00:00Z',
}

function render(appointments, locale = 'ar') {
  return mount(CommitteeSeatsList, {
    props: { appointments },
    global: createTestGlobals({ locale }),
  })
}

describe('CommitteeSeatsList', () => {
  it('lists each committee seat with its cycle, period and seat status', () => {
    const items = render([SEAT, { ...SEAT, id: 2, isSubstitute: true, status: 'removed' }]).findAll(
      '.committee-seats__item',
    )
    expect(items).toHaveLength(2)
    expect(items[0].find('.committee-seats__role').text()).toBe('عضو لجنة المقيّمين')
    expect(items[0].find('.committee-seats__cycle').text()).toBe('دورة جائزة مؤشر')
    expect(items[0].find('.committee-seats__period').text()).toContain('2026')
    expect(items[1].find('.committee-seats__role').text()).toBe('بديل في لجنة المقيّمين')
  })

  it('speaks English in English', () => {
    const wrapper = render([SEAT], 'en')
    expect(wrapper.find('.committee-seats__role').text()).toBe('Assessors committee member')
    expect(wrapper.attributes('aria-label')).toBe('Assessors committee seats')
  })
})
