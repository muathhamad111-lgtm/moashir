import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import BillingDetailsNotice from '@/components/payments/BillingDetailsNotice.vue'
import TransferBillingCard from '@/components/finance/TransferBillingCard.vue'
import { useEntityStore } from '@/stores/entity'
import { createTestGlobals } from '@/test-utils'

const { getMine } = vi.hoisted(() => ({ getMine: vi.fn() }))

vi.mock('@/services/entitiesService', () => ({ entitiesService: { getMine } }))

const BILLING = {
  addressBuildingNumber: '2154',
  addressStreet: 'شارع الأمير سلطان',
  addressDistrict: 'حي الروضة',
  addressCity: 'جدة',
  addressPostalCode: '23435',
  addressAdditionalNumber: '8027',
  addressShortCode: null,
  vatRegistered: true,
  vatNumber: '300060010000003',
  vatCertificate: null,
}

function mountWith(component, props = {}, profile = null) {
  const globals = createTestGlobals({ locale: 'ar' })
  setActivePinia(globals.pinia)
  if (profile) useEntityStore().profile = profile
  return mount(component, { props, global: globals })
}

describe('BillingDetailsNotice', () => {
  it('warns on the payment page and links to the billing section when billing is incomplete', async () => {
    const wrapper = mountWith(BillingDetailsNotice, {}, { billingComplete: false })
    await flushPromises()
    const notice = wrapper.get('[data-testid="billing-details-notice"]')
    expect(notice.attributes('role')).toBe('note')
    expect(notice.text()).toContain('العنوان الوطني والتسجيل الضريبي في ملف جهتك غير مكتملَين')
    expect(notice.get('a').attributes('href')).toContain('#profile-section-billing')
  })

  it('stays hidden once billing is complete', async () => {
    const wrapper = mountWith(BillingDetailsNotice, {}, { billingComplete: true })
    await flushPromises()
    expect(wrapper.find('[data-testid="billing-details-notice"]').exists()).toBe(false)
  })

  it('loads the own profile when the store is empty', async () => {
    getMine.mockResolvedValueOnce({ data: { billingComplete: false } })
    const wrapper = mountWith(BillingDetailsNotice)
    await flushPromises()
    expect(getMine).toHaveBeenCalled()
    expect(wrapper.find('[data-testid="billing-details-notice"]').exists()).toBe(true)
  })
})

describe('TransferBillingCard', () => {
  it('shows the billing rows and the incomplete flag to Finance', () => {
    const wrapper = mountWith(TransferBillingCard, {
      paymentId: 'p-1',
      billing: BILLING,
      complete: false,
    })
    expect(wrapper.get('[data-testid="transfer-billing-incomplete"]').text()).toContain(
      'بيانات الفوترة ناقصة',
    )
    expect(wrapper.text()).toContain('300060010000003')
    expect(wrapper.text()).toContain('شهادة التسجيل الضريبي')
    expect(wrapper.find('.transfer-billing__download').exists()).toBe(false)
  })

  it('hides the flag, skips the VAT rows for a non-registered payer and offers no download', () => {
    const wrapper = mountWith(TransferBillingCard, {
      paymentId: 'p-1',
      billing: { ...BILLING, vatRegistered: false, vatNumber: null },
      complete: true,
    })
    expect(wrapper.find('[data-testid="transfer-billing-incomplete"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('الرقم الضريبي')
  })

  it('offers the certificate download when one is uploaded', () => {
    const wrapper = mountWith(TransferBillingCard, {
      paymentId: 'p-1',
      billing: { ...BILLING, vatCertificate: { id: 'f-1', name: 'vat.pdf' } },
      complete: true,
    })
    expect(wrapper.find('.transfer-billing__download').exists()).toBe(true)
  })
})
