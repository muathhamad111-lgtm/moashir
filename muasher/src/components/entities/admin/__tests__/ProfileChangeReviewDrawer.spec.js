import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ProfileChangeReviewDrawer from '@/components/entities/admin/ProfileChangeReviewDrawer.vue'
import { profileChangeRequestsService } from '@/services/profileChangeRequestsService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/profileChangeRequestsService', () => ({
  profileChangeRequestsService: { show: vi.fn(), decide: vi.fn() },
}))

const REQUEST = {
  id: '6f1c2c1e-0a4b-4c0e-9d6a-1b2c3d4e5f60',
  status: 'pending',
  revision: 2,
  entity: { id: 7, reference: 'ENT-000007', name: { ar: 'جمعية الأمل', en: 'Al-Amal' } },
  items: ['employees_count', 'region_id', 'city_id', 'website_url'],
  staleFields: ['website_url'],
  reviewNote: null,
  changes: [
    {
      item: 'employees_count',
      kind: 'field',
      section: 'governance',
      label: { ar: 'عدد الموظفين', en: 'Number of employees' },
      oldValue: 12,
      newValue: 77,
      isStale: false,
      decision: null,
    },
    {
      item: 'region_id',
      kind: 'field',
      section: 'classification',
      label: { ar: 'المنطقة', en: 'Region' },
      oldValue: { id: 1, name: { ar: 'الرياض', en: 'Riyadh' } },
      newValue: { id: 2, name: { ar: 'مكة المكرمة', en: 'Makkah' } },
      isStale: false,
      decision: null,
    },
    {
      item: 'city_id',
      kind: 'field',
      section: 'classification',
      label: { ar: 'المدينة', en: 'City' },
      oldValue: { id: 10, name: { ar: 'الرياض', en: 'Riyadh' } },
      newValue: { id: 20, name: { ar: 'جدة', en: 'Jeddah' } },
      isStale: false,
      decision: null,
    },
    {
      item: 'website_url',
      kind: 'field',
      section: 'contact',
      label: { ar: 'الموقع الإلكتروني', en: 'Website' },
      oldValue: null,
      newValue: 'https://amal.org.sa',
      isStale: true,
      decision: null,
    },
  ],
}

async function mountDrawer(locale = 'ar') {
  const globals = createTestGlobals({ locale })
  const wrapper = mount(ProfileChangeReviewDrawer, {
    global: { ...globals, stubs: { teleport: true } },
    attachTo: document.body,
    props: { modelValue: true, requestId: REQUEST.id },
  })
  await flushPromises()
  return wrapper
}

function saveButton(wrapper) {
  return wrapper.findAll('button').find((button) => button.text() === 'حفظ القرار')
}

function checkbox(wrapper, item) {
  return wrapper.find(`input[name="approve-${item}"]`)
}

beforeEach(() => {
  vi.clearAllMocks()
  profileChangeRequestsService.show.mockResolvedValue({ data: structuredClone(REQUEST) })
  profileChangeRequestsService.decide.mockResolvedValue({
    data: { ...structuredClone(REQUEST), status: 'partially-approved' },
  })
})

describe('ProfileChangeReviewDrawer', () => {
  it('shows each item with its approved and proposed value in Arabic', async () => {
    const wrapper = await mountDrawer('ar')
    const rows = wrapper.findAll('.change-review__row')
    expect(rows).toHaveLength(4)
    expect(rows[0].text()).toContain('عدد الموظفين')
    expect(rows[0].text()).toContain('القيمة المعتمدة')
    expect(rows[0].text()).toContain('77')
    expect(rows[1].text()).toContain('مكة المكرمة')
    wrapper.unmount()
  })

  it('names the controls in English', async () => {
    const wrapper = await mountDrawer('en')
    expect(wrapper.text()).toContain('Proposed value')
    expect(wrapper.text()).toContain('Save the decision')
    wrapper.unmount()
  })

  it('keeps a stale field rejected and explains why', async () => {
    const wrapper = await mountDrawer()
    expect(checkbox(wrapper, 'website_url').element.disabled).toBe(true)
    expect(checkbox(wrapper, 'website_url').element.checked).toBe(false)
    expect(wrapper.find('.change-review__row--stale').text()).toContain(
      'تغيّرت القيمة المعتمدة بعد تقديم الطلب',
    )
    wrapper.unmount()
  })

  it('toggles the region and the city together', async () => {
    const wrapper = await mountDrawer()
    await checkbox(wrapper, 'city_id').setValue(false)
    expect(checkbox(wrapper, 'region_id').element.checked).toBe(false)
    wrapper.unmount()
  })

  it('asks for a reason before rejecting anything and sends the decided items', async () => {
    const wrapper = await mountDrawer()
    await saveButton(wrapper).trigger('click')
    await flushPromises()
    expect(profileChangeRequestsService.decide).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('اكتب سبب الرفض')

    await wrapper.find('textarea[name="reason"]').setValue('الموقع لا يخص الجهة المسجلة.')
    await saveButton(wrapper).trigger('click')
    await flushPromises()
    expect(profileChangeRequestsService.decide).toHaveBeenCalledWith(REQUEST.id, {
      revision: 2,
      approvedItems: ['employees_count', 'region_id', 'city_id'],
      reason: 'الموقع لا يخص الجهة المسجلة.',
    })
    expect(wrapper.emitted('decided')).toHaveLength(1)
    wrapper.unmount()
  })
})
