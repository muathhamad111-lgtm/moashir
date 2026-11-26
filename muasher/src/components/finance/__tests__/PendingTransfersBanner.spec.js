import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PendingTransfersBanner from '@/components/finance/PendingTransfersBanner.vue'
import { createTestGlobals } from '@/test-utils'

function mountBanner(props, locale) {
  return mount(PendingTransfersBanner, { props, global: createTestGlobals({ locale }) })
}

describe('pending transfers banner', () => {
  it.each([
    [1, 'تحويل واحد بانتظار الاعتماد'],
    [2, 'تحويلان بانتظار الاعتماد'],
    [3, '3 تحويلات بانتظار الاعتماد'],
    [11, '11 تحويلًا بانتظار الاعتماد'],
  ])('counts %i transfers in Arabic', (count, text) => {
    expect(mountBanner({ count }, 'ar').text()).toContain(text)
  })

  it('counts in English and filters the list to them, until it already shows them', async () => {
    const wrapper = mountBanner({ count: 1 }, 'en')
    expect(wrapper.text()).toContain('1 transfer awaiting approval')
    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('show')).toHaveLength(1)

    expect(mountBanner({ count: 4, showing: true }, 'en').find('button').exists()).toBe(false)
    expect(mountBanner({ count: 4 }, 'en').text()).toContain('4 transfers awaiting approval')
  })
})
