import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import InboxIndex from '@/views/admin/contact/Index.vue'
import InboxThread from '@/views/admin/contact/Thread.vue'
import { contactInboxService } from '@/services/contactInboxService'
import { useAuthStore } from '@/stores/auth'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { createTestGlobals } from '@/test-utils'
import { chooseNamedOption } from '@/test-utils/dropdown'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/contactInboxService', () => ({
  contactInboxService: {
    list: vi.fn(),
    assignees: vi.fn(),
    get: vi.fn(),
    assign: vi.fn(),
    addNote: vi.fn(),
    reply: vi.fn(),
    close: vi.fn(),
    reopen: vi.fn(),
  },
}))

const ID = '7d0c3c1e-4a51-4f0e-9a51-1a2b3c4d5e01'
const MESSAGE = {
  id: ID,
  reference: 'CNT-2026-000052',
  status: 'new',
  enquiryType: 'accredited_assessment',
  sender: {
    name: 'نورة بنت فهد العتيبي',
    email: 'info@namaa.example.sa',
    mobile: null,
    organization: null,
  },
  isLinked: true,
  linkedUser: null,
  entity: null,
  assignee: null,
  entryCount: 1,
  createdAt: '2026-11-04T08:10:00+00:00',
  lastActivityAt: '2026-11-04T08:10:00+00:00',
  closedBy: null,
  entries: [
    {
      id: 1,
      kind: 'message',
      body: 'نرجو التوضيح',
      author: { kind: 'sender', name: 'نورة' },
      createdAt: '2026-11-04T08:10:00+00:00',
    },
    {
      id: 2,
      kind: 'note',
      body: 'ملاحظة داخلية',
      author: { kind: 'staff', name: 'ريم' },
      createdAt: '2026-11-04T09:00:00+00:00',
    },
  ],
}

async function mountAt(component, location, { superAdmin = false, locale = 'ar' } = {}) {
  const globals = createTestGlobals({ locale })
  useAuthStore(globals.pinia).setUser(
    sessionUserFor(['system_admin'], { isSuperAdmin: superAdmin }),
  )
  await globals.router.push(location)
  const wrapper = mount(component, { global: globals, attachTo: document.body })
  await flushPromises()
  return wrapper
}

const THREAD = { name: ROUTE_NAMES.ADMIN_CONTACT_MESSAGE, params: { id: ID } }

beforeEach(() => {
  vi.clearAllMocks()
  vi.useRealTimers()
  contactInboxService.assignees.mockResolvedValue({ data: [{ id: 31, name: 'أ. هيفاء العنزي' }] })
  contactInboxService.list.mockResolvedValue({
    data: [MESSAGE],
    meta: {
      stats: { new: 1, inProgress: 0, replied: 0, closed: 3 },
      pagination: { page: 1, lastPage: 1, total: 1 },
    },
  })
  contactInboxService.get.mockResolvedValue({ data: MESSAGE })
})

describe('back-office contact inbox', () => {
  it('reads the status deep link, shows the stats and links each row to its thread', async () => {
    const wrapper = await mountAt(InboxIndex, {
      name: ROUTE_NAMES.ADMIN_CONTACT_MESSAGES,
      query: { status: 'new' },
    })
    expect(contactInboxService.list).toHaveBeenCalledWith(
      expect.objectContaining({ 'filter[status]': 'new', page: 1 }),
    )
    expect(wrapper.find('.stats-cards').text()).toContain('3')
    expect(wrapper.find('.contact-inbox__reference').attributes('href')).toBe(
      `/admin/contact-messages/${ID}`,
    )
    expect(wrapper.text()).toContain('مرسل مسجّل')
    wrapper.unmount()
  })

  it('searches after two characters only', async () => {
    vi.useFakeTimers()
    const wrapper = await mountAt(InboxIndex, { name: ROUTE_NAMES.ADMIN_CONTACT_MESSAGES })
    const input = wrapper.find('input[type="search"]')
    await input.setValue('ن')
    vi.advanceTimersByTime(400)
    await flushPromises()
    expect(contactInboxService.list).not.toHaveBeenCalledWith(
      expect.objectContaining({ search: 'ن' }),
    )
    await input.setValue('نورة')
    vi.advanceTimersByTime(400)
    await flushPromises()
    expect(contactInboxService.list).toHaveBeenLastCalledWith(
      expect.objectContaining({ search: 'نورة' }),
    )
    vi.useRealTimers()
    wrapper.unmount()
  })

  it('shows the thread with internal notes marked as private', async () => {
    const wrapper = await mountAt(InboxThread, THREAD)
    expect(contactInboxService.get).toHaveBeenCalledWith(ID)
    const entries = wrapper.findAll('.contact-entries__item')
    expect(entries).toHaveLength(2)
    expect(entries[1].attributes('data-entry-kind')).toBe('note')
    expect(entries[1].text()).toContain('لا يراها المرسل')
    wrapper.unmount()
  })

  it('replies, assigns and closes through the inbox endpoints', async () => {
    contactInboxService.reply.mockResolvedValue({ data: { ...MESSAGE, status: 'replied' } })
    contactInboxService.assign.mockResolvedValue({
      data: { ...MESSAGE, assignee: { id: 31, name: 'أ. هيفاء العنزي' } },
    })
    contactInboxService.close.mockResolvedValue({ data: { ...MESSAGE, status: 'closed' } })
    const wrapper = await mountAt(InboxThread, THREAD)
    await wrapper.find('textarea').setValue('شكرًا لتواصلكم، أرفقنا الإرشادات.')
    await wrapper.find('form.contact-composer').trigger('submit')
    await flushPromises()
    expect(contactInboxService.reply).toHaveBeenCalledWith(ID, {
      body: 'شكرًا لتواصلكم، أرفقنا الإرشادات.',
    })
    await chooseNamedOption(wrapper, 'assigneeId', '31')
    await wrapper.find('.contact-assign .btn').trigger('click')
    await flushPromises()
    expect(contactInboxService.assign).toHaveBeenCalledWith(ID, { assigneeId: 31 })
    const close = wrapper.findAll('button').find((button) => button.text() === 'إغلاق الرسالة')
    await close.trigger('click')
    await flushPromises()
    expect(contactInboxService.close).toHaveBeenCalledWith(ID, {})
    expect(wrapper.text()).toContain('إعادة فتح الرسالة')
    wrapper.unmount()
  })

  it('rejects a too short reply locally and reloads after a closed-message conflict', async () => {
    contactInboxService.reply.mockRejectedValue({
      normalized: {
        status: 409,
        code: 'contact.message_closed',
        message: 'مغلقة',
        fieldErrors: {},
      },
    })
    const wrapper = await mountAt(InboxThread, THREAD)
    await wrapper.find('textarea').setValue('ش')
    await wrapper.find('form.contact-composer').trigger('submit')
    expect(contactInboxService.reply).not.toHaveBeenCalled()
    await wrapper.find('textarea').setValue('رد صالح للإرسال')
    await wrapper.find('form.contact-composer').trigger('submit')
    await flushPromises()
    expect(contactInboxService.get).toHaveBeenCalledTimes(2)
    wrapper.unmount()
  })

  it('asks the Super Admin for a reason before any inbox write', async () => {
    contactInboxService.close.mockResolvedValue({ data: { ...MESSAGE, status: 'closed' } })
    const wrapper = await mountAt(InboxThread, THREAD, { superAdmin: true })
    const close = wrapper.findAll('button').find((button) => button.text() === 'إغلاق الرسالة')
    await close.trigger('click')
    await flushPromises()
    expect(contactInboxService.close).not.toHaveBeenCalled()
    const reason = wrapper.findAllComponents({ name: 'ReasonDialog' }).at(-1)
    await reason.props('action')('سبب إغلاق الرسالة من مدير النظام الرئيسي')
    await flushPromises()
    expect(contactInboxService.close).toHaveBeenCalledWith(ID, {
      reason: 'سبب إغلاق الرسالة من مدير النظام الرئيسي',
    })
    wrapper.unmount()
  })

  it('shows the missing state for an unknown message', async () => {
    contactInboxService.get.mockRejectedValue({ normalized: { status: 404, message: 'x' } })
    const wrapper = await mountAt(InboxThread, THREAD, { locale: 'en' })
    expect(wrapper.text()).toContain('Message not found')
    wrapper.unmount()
  })
})
