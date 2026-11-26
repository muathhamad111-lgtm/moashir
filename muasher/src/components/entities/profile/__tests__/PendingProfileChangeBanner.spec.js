import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PendingProfileChangeBanner from '@/components/entities/profile/PendingProfileChangeBanner.vue'
import { createTestGlobals } from '@/test-utils'

const PENDING = {
  id: 'r-1',
  revision: 1,
  changes: [
    {
      item: 'employees_count',
      kind: 'field',
      section: 'governance',
      label: { ar: 'عدد الموظفين', en: 'Number of employees' },
      oldValue: 12,
      newValue: 77,
    },
    {
      item: 'logo',
      kind: 'file',
      section: null,
      label: { ar: 'شعار الجهة', en: 'Entity logo' },
      oldValue: null,
      newValue: { id: 'f-1', name: 'logo.png', url: 'https://files.test/logo.png' },
      removes: false,
    },
  ],
}

function mountBanner({ locale = 'ar', canEdit = true, pendingChange = PENDING } = {}) {
  return mount(PendingProfileChangeBanner, {
    global: createTestGlobals({ locale }),
    props: { pendingChange, canEdit },
  })
}

describe('PendingProfileChangeBanner', () => {
  it('says the changes await the administration and lists them on request', async () => {
    const wrapper = mountBanner()
    expect(wrapper.find('.pending-change__title').text()).toBe('بانتظار اعتماد الإدارة')
    expect(wrapper.find('.pending-change__list').exists()).toBe(false)
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'عرض التعديلات المقترحة')
      .trigger('click')
    const rows = wrapper.findAll('.pending-change__row')
    expect(rows).toHaveLength(2)
    expect(rows[0].text()).toContain('عدد الموظفين')
    expect(rows[0].text()).toContain('77')
    expect(rows[1].find('a').attributes('href')).toBe('https://files.test/logo.png')
  })

  it('speaks English', () => {
    const wrapper = mountBanner({ locale: 'en' })
    expect(wrapper.find('.pending-change__title').text()).toBe(
      'Awaiting the administration’s approval',
    )
    expect(wrapper.text()).toContain('Cancel the request')
  })

  it('lets only an entity that can edit cancel its request', async () => {
    const editable = mountBanner()
    await editable
      .findAll('button')
      .find((button) => button.text() === 'إلغاء الطلب')
      .trigger('click')
    expect(editable.emitted('cancel')).toHaveLength(1)

    const readOnly = mountBanner({ canEdit: false })
    expect(readOnly.text()).not.toContain('إلغاء الطلب')
  })

  it('still shows the notice when the details could not be loaded', () => {
    const wrapper = mountBanner({ pendingChange: null })
    expect(wrapper.text()).toContain('تعذّر تحميل التعديلات المقترحة.')
  })
})
