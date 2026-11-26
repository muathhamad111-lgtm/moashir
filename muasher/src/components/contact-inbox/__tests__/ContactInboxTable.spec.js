import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import ContactInboxTable from '@/components/contact-inbox/ContactInboxTable.vue'
import { useTableColumnsStore } from '@/stores/tableColumns'
import { createTestGlobals } from '@/test-utils'

const ROW = {
  id: '7d0c3c1e-4a51-4f0e-9a51-1a2b3c4d5e01',
  reference: 'CNT-2026-000052',
  status: 'closed',
  inboxStatus: 'replied',
  enquiryType: 'accredited_assessment',
  sender: { name: 'نورة العتيبي', organization: 'مؤسسة نماء للتنمية', locale: 'ar' },
  excerpt: 'نرجو التوضيح',
  entity: { id: 5001, reference: 'ENT-005001', name: { ar: 'مؤسسة نماء', en: 'Namaa' } },
  assignee: null,
  entryCount: 3,
  createdAt: '2026-11-04T08:10:00+00:00',
  lastActivityAt: '2026-11-05T08:10:00+00:00',
  repliedAt: null,
  closedAt: '2026-11-06T08:10:00+00:00',
  closedBy: { id: 31, name: 'هيفاء العنزي' },
}

describe('ContactInboxTable optional columns', () => {
  it('shows the closing handler, the linked entity, the entries and an empty reply date once chosen', async () => {
    const globals = createTestGlobals({ locale: 'en' })
    setActivePinia(globals.pinia)
    const wrapper = mount(ContactInboxTable, {
      props: { items: [ROW], status: 'success' },
      global: globals,
    })
    await flushPromises()
    useTableColumnsStore().choices = {
      'contact-inbox': ['reference', 'repliedAt', 'closedBy', 'entity', 'entries'],
    }
    await flushPromises()
    const cells = wrapper.findAll('tbody td').map((cell) => cell.text())
    expect(cells.slice(1)).toEqual([
      'CNT-2026-000052',
      '',
      'هيفاء العنزي',
      expect.stringContaining('ENT-005001'),
      '3',
    ])
    expect(cells[4]).toContain('Namaa')
  })
})
