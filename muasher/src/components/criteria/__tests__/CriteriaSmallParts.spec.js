import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import WeightBudget from '@/components/criteria/WeightBudget.vue'
import GuidingPointForm from '@/components/criteria/GuidingPointForm.vue'
import CriteriaRebindSummary from '@/components/assessment/self/CriteriaRebindSummary.vue'
import { createTestGlobals } from '@/test-utils'

function mountWith(component, props, locale = 'en') {
  return mount(component, {
    props,
    global: { ...createTestGlobals({ locale }), stubs: { teleport: true } },
  })
}

describe('WeightBudget', () => {
  it('reports what is still to allocate, an over-allocation and a full allocation', () => {
    const base = { capacity: '400.00', capacityLabel: 'Sub-criterion weight' }

    expect(mountWith(WeightBudget, { ...base, allocated: '350.00' }).text()).toContain(
      '50.00 still to allocate.',
    )
    expect(mountWith(WeightBudget, { ...base, allocated: '420.00' }).text()).toContain(
      'Over-allocated by 20.00.',
    )
    expect(mountWith(WeightBudget, { ...base, allocated: '400.00' }).text()).toContain(
      'The weight is fully allocated.',
    )
  })
})

describe('GuidingPointForm', () => {
  const SUB = {
    id: 9,
    weight: '400.00',
    remainingPointWeight: '150.00',
    distributeEqually: false,
  }

  it('starts a new point with the remaining weight of the sub-criterion', () => {
    const wrapper = mountWith(GuidingPointForm, { modelValue: true, sub: SUB, save: vi.fn() })

    expect(wrapper.find('input[name="pointWeight"]').element.value).toBe('150.00')
  })

  it('refuses a weight above the remaining weight and saves within it', async () => {
    const save = vi.fn().mockResolvedValue({})
    const wrapper = mountWith(GuidingPointForm, { modelValue: true, sub: SUB, save })
    await wrapper.find('textarea[name="pointDescriptionAr"]').setValue('وثيقة سياسة معتمدة')
    await wrapper
      .find('textarea[name="pointDescriptionEn"]')
      .setValue('An approved policy document')
    await wrapper.find('input[name="pointWeight"]').setValue('200')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(save).not.toHaveBeenCalled()

    await wrapper.find('input[name="pointWeight"]').setValue('150')
    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() => expect(save).toHaveBeenCalled())
    expect(save.mock.calls[0][0].payload.weight).toBe('150.00')
  })
})

describe('CriteriaRebindSummary', () => {
  const UPDATE = {
    fromVersionNumber: 1,
    toVersionNumber: 2,
    movedAt: '2026-11-18T09:00:00Z',
    scope: 'all',
    trigger: 'criteria_edit',
    kept: [{ level: 'point', code: 'M1.2.1', label: { ar: 'سياسة', en: 'Policy' } }],
    reset: [{ level: 'sub', code: 'M1.1', label: null }],
    added: [],
  }

  it('lists the kept, reset and added items of a move between criteria versions', () => {
    const text = mountWith(CriteriaRebindSummary, { update: UPDATE }).text()

    expect(text).toContain('Your draft moved from criteria version 1 to version 2')
    expect(text).toContain('1 item kept its score')
    expect(text).toContain('Policy')
    expect(text).toContain('Item no longer named in the criteria')
    expect(text).toContain('No new item')
  })
})
