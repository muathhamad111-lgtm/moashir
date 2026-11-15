import { describe, expect, it } from 'vitest'
import { ref } from 'vue'
import { mount } from '@vue/test-utils'
import MainCriteriaTable from '@/components/criteria/MainCriteriaTable.vue'
import SubCriteriaTable from '@/components/criteria/SubCriteriaTable.vue'
import GuidingPointsTable from '@/components/criteria/GuidingPointsTable.vue'
import { createTestGlobals } from '@/test-utils'

const ACCESS = { canEditTree: ref(true), canCorrectText: ref(false) }

const MAIN = {
  id: 1,
  code: 'M1',
  name: { ar: 'القيادة الإدارية', en: 'Administrative leadership' },
  group: 'enablers',
  order: 1,
  weight: '100.00',
  distributeEqually: true,
  weightBalanced: true,
  remainingWeight: '0.00',
  subCount: 2,
  active: true,
}

const SUB = {
  id: 101,
  code: 'M1.1',
  name: { ar: 'الحوكمة', en: 'Governance' },
  order: 1,
  weight: '50.00',
  evaluationType: 'direct',
  pointCount: 0,
  active: true,
}

const POINT = {
  id: 1001,
  code: 'M1.1.1',
  description: { ar: 'ميثاق حوكمة معتمد', en: 'An approved governance charter' },
  order: 1,
  weight: '25.00',
  attachments: [],
  active: true,
}

function render(component, props) {
  return mount(component, { props, global: createTestGlobals({ locale: 'en' }) })
}

function headers(wrapper) {
  return wrapper.findAll('th').map((cell) => cell.text())
}

describe('criteria management tables', () => {
  it('lists main criteria by name without a code column', () => {
    const wrapper = render(MainCriteriaTable, {
      items: [MAIN],
      status: 'success',
      access: ACCESS,
    })
    expect(headers(wrapper)).not.toContain('Code')
    expect(wrapper.text()).toContain('Administrative leadership')
    expect(wrapper.text()).not.toContain('M1')
    expect(wrapper.find('[aria-label="Actions: Administrative leadership"]').exists()).toBe(true)
  })

  it('lists sub-criteria by name without a code column', () => {
    const wrapper = render(SubCriteriaTable, { subs: [SUB], mainWeight: '100.00', access: ACCESS })
    expect(headers(wrapper)).not.toContain('Code')
    expect(wrapper.text()).not.toContain('M1.1')
    expect(wrapper.find('[aria-label="Actions: Governance"]').exists()).toBe(true)
  })

  it('names guiding points by their description without a code column', () => {
    const wrapper = render(GuidingPointsTable, {
      points: [POINT],
      subWeight: '50.00',
      access: ACCESS,
    })
    expect(headers(wrapper)).not.toContain('Code')
    expect(wrapper.text()).not.toContain('M1.1.1')
    expect(wrapper.find('[aria-label="Actions: An approved governance charter"]').exists()).toBe(
      true,
    )
  })
})
