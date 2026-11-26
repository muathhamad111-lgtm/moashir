import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import EntityAccountTimeline from '@/components/entities/admin/EntityAccountTimeline.vue'
import { entitiesService } from '@/services/entitiesService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/entitiesService', () => ({ entitiesService: { timeline: vi.fn() } }))

const BASE = { actor: null, status: null, reason: null, fields: [], change: null, email: null }
const FEED = [
  {
    ...BASE,
    id: 'deactivated:7',
    kind: 'deactivated',
    occurredAt: '2026-11-01T13:00:00Z',
    reason: 'توقف النشاط',
  },
  {
    ...BASE,
    id: 'email_change_requested:3',
    kind: 'email_change_requested',
    occurredAt: '2026-11-01T12:00:00Z',
    email: { old: 'old@alamal.example.sa', new: 'new@alamal.example.sa' },
  },
  {
    ...BASE,
    id: 'profile_change_decided:9',
    kind: 'profile_change_decided',
    occurredAt: '2026-11-01T11:00:00Z',
    status: 'approved',
    fields: ['employees_count'],
    actor: {
      name: 'منيرة العتيبي',
      roles: [{ key: 'system_admin', label: { ar: 'مدير النظام', en: 'System Administrator' } }],
    },
  },
  { ...BASE, id: 'created:7', kind: 'created', occurredAt: '2026-10-01T09:00:00Z' },
]

async function render() {
  const wrapper = mount(EntityAccountTimeline, {
    props: { entityId: 7 },
    global: createTestGlobals({ locale: 'ar' }),
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => vi.clearAllMocks())

describe('EntityAccountTimeline — the «سجل الحساب» tab', () => {
  it('reads the first page of the server timeline and lists it in the order received', async () => {
    entitiesService.timeline.mockResolvedValue({
      data: FEED,
      meta: { pagination: { lastPage: 1 } },
    })
    const wrapper = await render()
    expect(entitiesService.timeline).toHaveBeenCalledWith(7, { page: 1, perPage: 15 })
    const titles = wrapper.findAll('.entity-timeline__head strong').map((title) => title.text())
    expect(titles).toEqual([
      'إيقاف الحساب',
      'طلب تغيير البريد الإلكتروني',
      'البتّ في طلب تعديل البيانات',
      'تسجيل الجهة',
    ])
  })

  it('shows the reason, the e-mail addresses, the decision and the deciding staff', async () => {
    entitiesService.timeline.mockResolvedValue({ data: FEED, meta: {} })
    const wrapper = await render()
    const [deactivated, email, decided] = wrapper.findAll('.entity-timeline__item')
    expect(deactivated.find('.entity-timeline__detail').text()).toContain('توقف النشاط')
    expect(email.find('.entity-timeline__after').text()).toBe('new@alamal.example.sa')
    expect(email.find('.entity-timeline__after').attributes('dir')).toBe('ltr')
    expect(decided.find('.entity-timeline__detail').text()).toContain('معتمد')
    expect(decided.find('.entity-timeline__actor').text()).toContain('منيرة العتيبي')
  })

  it('shows the empty and error states', async () => {
    entitiesService.timeline.mockResolvedValueOnce({ data: [], meta: {} })
    expect((await render()).find('.empty-state').exists()).toBe(true)
    entitiesService.timeline.mockRejectedValueOnce(new Error('down'))
    expect((await render()).find('.error-state').exists()).toBe(true)
  })
})
