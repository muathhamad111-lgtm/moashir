import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ProfileChangeHistory from '@/components/entities/ProfileChangeHistory.vue'
import OpenRequestsList from '@/components/entities/admin/OpenRequestsList.vue'
import { createTestGlobals } from '@/test-utils'

const ROLE = { key: 'system_admin', label: { ar: 'مدير النظام', en: 'System Administrator' } }

const CHANGES = [
  { id: 3, field: 'region_id', fieldLabel: { ar: 'المنطقة', en: 'Region' }, oldValue: { id: 1, code: 'riyadh', name: { ar: 'منطقة الرياض', en: 'Riyadh Region' } }, newValue: { id: 4, code: 'makkah', name: { ar: 'منطقة مكة المكرمة', en: 'Makkah Region' } }, changedBy: 'staff', changedByStaff: { name: 'منيرة بنت خالد العتيبي', roles: [ROLE] }, changedAt: '2026-11-01T10:00:00Z' },
  { id: 2, field: 'has_strategic_plan', fieldLabel: { ar: 'الخطة الاستراتيجية', en: 'Strategic plan' }, oldValue: false, newValue: true, changedBy: 'staff', changedByStaff: { name: null, roles: [] }, changedAt: '2026-10-30T10:00:00Z' },
  { id: 1, field: 'entity_type', fieldLabel: { ar: 'نوع الجهة', en: 'Entity type' }, oldValue: 'civil_foundation', newValue: 'civil_association', changedBy: 'system', changedByStaff: null, changedAt: '2026-10-29T10:00:00Z' },
]

function mountHistory(props, locale = 'ar') {
  return mount(ProfileChangeHistory, { props, global: createTestGlobals({ locale }) })
}

const loader = () => Promise.resolve({ data: CHANGES, meta: { pagination: { page: 1, perPage: 10, total: 3, lastPage: 1 } } })

describe('ProfileChangeHistory', () => {
  it('shows the staff name with the first role label for staff readers', async () => {
    const wrapper = mountHistory({ loader, showStaffIdentity: true })
    await flushPromises()
    const rows = wrapper.findAll('tbody tr').map((row) => row.text())
    expect(rows[0]).toContain('منيرة بنت خالد العتيبي — مدير النظام')
    expect(rows[0]).toContain('منطقة الرياض')
    expect(rows[0]).toContain('منطقة مكة المكرمة')
    expect(rows[1]).toContain('موظف')
    expect(rows[2]).toContain('النظام')
  })

  it('never shows staff names in the entity own history', async () => {
    const ownChanges = CHANGES.map((change) => Object.fromEntries(Object.entries(change).filter(([key]) => key !== 'changedByStaff')))
    const own = () => Promise.resolve({ data: ownChanges, meta: {} })
    const wrapper = mountHistory({ loader: own }, 'en')
    await flushPromises()
    expect(wrapper.text()).not.toContain('منيرة')
    expect(wrapper.findAll('tbody tr')[0].text()).toContain('Staff member')
    expect(wrapper.text()).toContain('Civil association')
  })

  it('shows the empty and error states', async () => {
    const empty = mountHistory({ loader: () => Promise.resolve({ data: [], meta: {} }) }, 'en')
    await flushPromises()
    expect(empty.text()).toContain('No profile changes have been recorded yet.')
    const failing = mountHistory({ loader: () => Promise.reject({ response: { status: 500 }, normalized: { message: 'Failed' } }) }, 'en')
    await flushPromises()
    expect(failing.find('.error-state').exists()).toBe(true)
  })
})

describe('OpenRequestsList', () => {
  it('reads is_frozen per request', () => {
    const wrapper = mount(OpenRequestsList, {
      props: { requests: [{ number: 'ACC-2026-000014', path: 'accredited', status: 'under-review', isFrozen: true }, { number: 'SLF-2026-000002', path: 'self', status: 'draft', isFrozen: false }] },
      global: createTestGlobals({ locale: 'en' }),
    })
    const rows = wrapper.findAll('.open-requests__row')
    expect(rows[0].find('.frozen-badge').exists()).toBe(true)
    expect(rows[1].find('.frozen-badge').exists()).toBe(false)
  })
})
