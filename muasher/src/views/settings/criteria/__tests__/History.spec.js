import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import History from '@/views/settings/criteria/History.vue'
import { criteriaService } from '@/services/criteriaService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/criteriaService', () => ({
  criteriaService: { listEditEvents: vi.fn() },
}))

const STRUCTURAL = {
  id: 2,
  kind: 'structural',
  scope: 'reset_open',
  fromVersion: { id: 1, versionNumber: 1, label: null },
  toVersion: { id: 3, versionNumber: 2, label: { ar: 'مراجعة 2027', en: '2027 review' } },
  changeCount: 1,
  scoreAffectingCount: 1,
  changes: [
    {
      nodeType: 'sub',
      code: 'M1.1',
      change: 'modified',
      fields: ['weight'],
      before: { weight: '400.00' },
      after: { weight: '500.00' },
      scoreAffecting: true,
    },
  ],
  affectedOpenCount: 3,
  reason: 'Annual review of the model',
  editedBy: { id: 7, name: 'Khalid Al-Omari' },
  createdAt: '2026-11-18T09:30:00Z',
}

const CORRECTION = {
  ...STRUCTURAL,
  id: 3,
  kind: 'text_correction',
  scope: 'all',
  toVersion: null,
  changes: [],
  changeCount: 0,
  scoreAffectingCount: 0,
  affectedOpenCount: 0,
  reason: null,
  editedBy: null,
}

async function mountHistory(locale = 'en') {
  criteriaService.listEditEvents.mockResolvedValue({
    data: [CORRECTION, STRUCTURAL],
    meta: { pagination: { page: 1, perPage: 20, total: 2, lastPage: 1 } },
  })
  const wrapper = mount(History, { global: createTestGlobals({ locale }) })
  await flushPromises()
  return wrapper
}

describe('criteria History', () => {
  it('lists each edit with its versions, scope, reason, changes, affected requests and editor', async () => {
    const wrapper = await mountHistory()
    const rows = wrapper.findAll('tbody tr')

    expect(criteriaService.listEditEvents).toHaveBeenCalledWith({ page: 1, perPage: 20 })
    expect(rows).toHaveLength(2)
    expect(rows[1].text()).toContain('Version 1')
    expect(rows[1].text()).toContain('to Version 2 — 2027 review')
    expect(rows[1].text()).toContain('Reset the open requests')
    expect(rows[1].text()).toContain('Reason: Annual review of the model')
    expect(rows[1].text()).toContain('3 open requests')
    expect(rows[1].text()).toContain('Khalid Al-Omari')
    expect(rows[1].find('.criteria-change-list__item').text()).toContain('400.00 → 500.00')
  })

  it('labels a text correction and an unknown editor', async () => {
    const row = (await mountHistory()).findAll('tbody tr')[0]

    expect(row.text()).toContain('Text correction')
    expect(row.text()).toContain('No change details')
    expect(row.text()).toContain('Unknown')
  })

  it('renders in Arabic', async () => {
    const wrapper = await mountHistory('ar')

    expect(wrapper.find('h1').text()).toBe('سجل تعديلات المعايير')
    expect(wrapper.findAll('tbody tr')[1].text()).toContain('3 من الطلبات المفتوحة')
  })
})
