import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { useBillingRows } from '@/composables/useBillingRows'
import { createTestGlobals } from '@/test-utils'

const BILLING = {
  addressBuildingNumber: '7421',
  addressStreet: 'King Fahd Road',
  addressDistrict: 'Al Olaya',
  addressCity: 'Riyadh',
  addressPostalCode: '12211',
  addressAdditionalNumber: '3344',
  addressShortCode: 'RAAA7421',
  vatRegistered: true,
  vatNumber: '300000000000003',
  vatCertificate: { name: 'vat.pdf' },
}

function billingRows() {
  let rows = null
  mount(
    defineComponent({
      setup() {
        rows = useBillingRows()
        return () => null
      },
    }),
    { global: createTestGlobals({ locale: 'ar' }) },
  )
  return rows
}

describe('useBillingRows — the staff record tabs', () => {
  it('lists the national address fields only in the address rows', () => {
    const keys = billingRows()
      .addressRowsOf(BILLING)
      .map((row) => row.key)
    expect(keys).toEqual([
      'addressBuildingNumber',
      'addressStreet',
      'addressDistrict',
      'addressCity',
      'addressPostalCode',
      'addressAdditionalNumber',
      'addressShortCode',
    ])
  })

  it('lists the VAT registration in the tax rows, with its number only when registered', () => {
    const rows = billingRows()
    expect(rows.taxRowsOf(BILLING).map((row) => row.key)).toEqual([
      'vatRegistered',
      'vatNumber',
      'vatCertificate',
    ])
    expect(rows.taxRowsOf({ vatRegistered: false }).map((row) => row.key)).toEqual([
      'vatRegistered',
    ])
  })

  it('keeps both groups together for the transfer billing card', () => {
    const rows = billingRows()
    expect(rows.rowsOf(BILLING)).toHaveLength(10)
    expect(rows.addressRowsOf(null)).toEqual([])
    expect(rows.taxRowsOf(null)).toEqual([])
  })
})
