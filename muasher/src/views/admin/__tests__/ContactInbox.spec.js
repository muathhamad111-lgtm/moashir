import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Inbox from '@/views/admin/contact/Inbox.vue'
import { contactInboxService } from '@/services/contactInboxService'
import { useAuthStore } from '@/stores/auth'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { createTestGlobals } from '@/test-utils'
import { chooseNamedOption } from '@/test-utils/dropdown'
import { sessionUserFor } from '@/test-utils/session'
import { openListSearch } from '@/test-utils/listToolbar'

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
  inboxStatus: 'new',
  enquiryType: 'accredited_assessment',
  sender: {
    name: 'نورة بنت فهد العتيبي',
    email: 'info@namaa.example.sa',
    mobile: null,
    organization: null,
  },
  isLinked: true,
  excerpt: 'نرجو التوضيح',
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

const CLOSED_REPLIED = {
  ...MESSAGE,
  id: '7d0c3c1e-4a51-4f0e-9a51-1a2b3c4d5e04',
  reference: 'CNT-2026-000040',
  status: 'closed',
  inboxStatus: 'replied',
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.useRealTimers()
  // jsdom has no scrolling; the inbox scrolls the open message's row into view.
  Element.prototype.scrollIntoView = vi.fn()
  contactInboxService.assignees.mockResolvedValue({ data: [{ id: 31, name: 'أ. هيفاء العنزي' }] })
  contactInboxService.list.mockResolvedValue({
    data: [MESSAGE],
    meta: {
      stats: { new: 1, assigned: 0, replied: 2, awaitingReply: 1 },
      pagination: { page: 1, lastPage: 1, total: 1 },
    },
  })
  contactInboxService.get.mockResolvedValue({ data: MESSAGE })
})

describe('back-office contact inbox', () => {
  it('reads the status deep link, counts each status on its chip and lists every message full width', async () => {
    contactInboxService.list.mockResolvedValue({
      data: [MESSAGE, CLOSED_REPLIED],
      meta: {
        stats: { new: 1, assigned: 0, replied: 2, awaitingReply: 1 },
        pagination: { page: 1, lastPage: 1, total: 2 },
      },
    })
    const wrapper = await mountAt(Inbox, {
      name: ROUTE_NAMES.ADMIN_CONTACT_MESSAGES,
      query: { status: 'new' },
    })
    expect(contactInboxService.list).toHaveBeenCalledWith(
      expect.objectContaining({ 'filter[inboxStatus]': 'new', page: 1 }),
    )
    const chips = wrapper.findAll('.contact-chips__chip')
    expect(chips.map((chip) => chip.text())).toEqual([
      'الكل4',
      'جديدة1',
      'مسندة0',
      'تم الرد2',
      'بانتظار الرد1',
    ])
    expect(chips[1].attributes('aria-pressed')).toBe('true')
    expect(wrapper.find('.contact-panel').exists()).toBe(false)
    const rows = wrapper.findAll('.table__row')
    expect(rows).toHaveLength(2)
    const sender = rows[0].find('.contact-table__sender')
    expect(sender.attributes('href')).toBe(`/admin/contact-messages/${ID}?status=new`)
    expect(sender.text()).toContain('نرجو التوضيح')
    expect(rows[0].find('.contact-status').text()).toBe('جديدة')
    expect(rows[1].find('.contact-status').text()).toBe('تم الرد')
    expect(rows[1].text()).not.toContain('مغلقة')
    wrapper.unmount()
  })

  it('searches after two characters only', async () => {
    vi.useFakeTimers()
    const wrapper = await mountAt(Inbox, { name: ROUTE_NAMES.ADMIN_CONTACT_MESSAGES })
    const input = await openListSearch(wrapper)
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

  it('filters by status from a chip', async () => {
    const wrapper = await mountAt(Inbox, { name: ROUTE_NAMES.ADMIN_CONTACT_MESSAGES })
    await wrapper.findAll('.contact-chips__chip')[4].trigger('click')
    await flushPromises()
    expect(contactInboxService.list).toHaveBeenLastCalledWith(
      expect.objectContaining({ 'filter[inboxStatus]': 'awaiting-reply', page: 1 }),
    )
    wrapper.unmount()
  })

  it('opens the message beside the list: an aligned header, the facts row and chat sides, notes marked private', async () => {
    const wrapper = await mountAt(Inbox, THREAD)
    expect(contactInboxService.get).toHaveBeenCalledWith(ID)
    expect(wrapper.find('.contact-list__item--selected').exists()).toBe(true)
    expect(wrapper.find('.contact-desk').classes()).toContain('contact-desk--open')
    expect(wrapper.find('.contact-controls .contact-status').text()).toBe('جديدة')
    expect(wrapper.find('.contact-panel__title').text()).toBe('نرجو التوضيح')
    const lines = wrapper.findAll('.contact-panel__line')
    expect(lines[0].text()).toContain('نورة بنت فهد العتيبي')
    expect(lines[0].text()).toContain('مرسل مسجّل')
    expect(lines[1].text()).toContain('CNT-2026-000052')
    expect(lines[1].text()).toContain('info@namaa.example.sa')
    const facts = wrapper.findAll('.contact-controls dt').map((term) => term.text())
    expect(facts).toEqual(['الحالة', 'النوع', 'المسؤول عن الرسالة'])
    const entries = wrapper.findAll('.contact-entries__item')
    expect(entries).toHaveLength(2)
    expect(entries[0].classes()).toContain('contact-entries__item--sender')
    expect(entries[1].attributes('data-entry-kind')).toBe('note')
    expect(entries[1].classes()).toContain('contact-entries__item--team')
    expect(entries[1].text()).toContain('لا يراها المرسل')
    expect(entries[1].find('[data-icon="lock"]').exists()).toBe(true)
    wrapper.unmount()
  })

  it('moves with the arrow keys, closes with Escape back to the full table and leaves the keys alone then', async () => {
    const SECOND = {
      ...MESSAGE,
      id: '7d0c3c1e-4a51-4f0e-9a51-1a2b3c4d5e09',
      reference: 'CNT-2026-000099',
    }
    contactInboxService.list.mockResolvedValue({
      data: [MESSAGE, SECOND],
      meta: {
        stats: { new: 2, assigned: 0, replied: 0, awaitingReply: 0 },
        pagination: { page: 1, lastPage: 1, total: 2 },
      },
    })
    const wrapper = await mountAt(Inbox, THREAD)
    const router = wrapper.vm.$router
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown' }))
    await flushPromises()
    expect(router.currentRoute.value.params.id).toBe(SECOND.id)
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await flushPromises()
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.ADMIN_CONTACT_MESSAGES)
    expect(wrapper.find('.contact-panel').exists()).toBe(false)
    expect(wrapper.findAll('.table__row')).toHaveLength(2)
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown' }))
    await flushPromises()
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.ADMIN_CONTACT_MESSAGES)
    wrapper.unmount()
  })

  it('names the statuses and the table in English', async () => {
    const wrapper = await mountAt(
      Inbox,
      { name: ROUTE_NAMES.ADMIN_CONTACT_MESSAGES },
      { locale: 'en' },
    )
    const chips = wrapper.findAll('.contact-chips__chip').map((chip) => chip.text())
    expect(chips).toEqual(['All4', 'New1', 'Assigned0', 'Replied2', 'Awaiting reply1'])
    expect(wrapper.find('.table__head').text()).toContain('Status')
    wrapper.unmount()
  })

  it('lets user-written Arabic keep its own direction in the English table', async () => {
    contactInboxService.list.mockResolvedValue({
      data: [{ ...MESSAGE, assignee: { id: 31, name: 'أ. هيفاء العنزي' } }],
      meta: {
        stats: { new: 1, assigned: 0, replied: 0, awaitingReply: 0 },
        pagination: { page: 1, lastPage: 1, total: 1 },
      },
    })
    const wrapper = await mountAt(
      Inbox,
      { name: ROUTE_NAMES.ADMIN_CONTACT_MESSAGES },
      { locale: 'en' },
    )
    expect(wrapper.find('.contact-table__name').attributes('dir')).toBe('auto')
    expect(wrapper.find('.contact-table__excerpt').attributes('dir')).toBe('auto')
    // <bdi> isolates the name and resolves its direction from its own text.
    const names = wrapper.findAll('bdi').map((node) => node.text())
    expect(names).toContain('أ. هيفاء العنزي')
    wrapper.unmount()
  })

  it('lets user-written Arabic keep its own direction in the English list and open message', async () => {
    contactInboxService.get.mockResolvedValue({
      data: { ...MESSAGE, sender: { ...MESSAGE.sender, organization: 'جمعية نماء' } },
    })
    const wrapper = await mountAt(Inbox, THREAD, { locale: 'en' })
    const autoText = (selector) => wrapper.find(selector).attributes('dir')
    expect(autoText('.contact-list__sender')).toBe('auto')
    expect(autoText('.contact-list__excerpt')).toBe('auto')
    expect(wrapper.find('.contact-list__meta span:last-child').attributes('dir')).toBe('auto')
    expect(autoText('.contact-panel__title')).toBe('auto')
    expect(autoText('.contact-panel__name')).toBe('auto')
    const organization = wrapper
      .findAll('.contact-panel__line span')
      .find((node) => node.text() === 'جمعية نماء')
    expect(organization?.attributes('dir')).toBe('auto')
    for (const entry of wrapper.findAll('.contact-entries__item')) {
      expect(entry.find('.contact-entries__author').attributes('dir')).toBe('auto')
      expect(entry.find('.contact-entries__body').attributes('dir')).toBe('auto')
    }
    wrapper.unmount()
  })

  it('replies, assigns and closes through the inbox endpoints', async () => {
    contactInboxService.reply.mockResolvedValue({
      data: { ...MESSAGE, status: 'replied', inboxStatus: 'replied' },
    })
    contactInboxService.assign.mockResolvedValue({
      data: { ...MESSAGE, assignee: { id: 31, name: 'أ. هيفاء العنزي' } },
    })
    contactInboxService.close.mockResolvedValue({
      data: {
        ...MESSAGE,
        status: 'closed',
        closedAt: '2026-11-05T09:00:00+00:00',
        closedBy: { id: 31, name: 'أ. هيفاء العنزي' },
      },
    })
    const wrapper = await mountAt(Inbox, THREAD)
    await wrapper.find('textarea').setValue('شكرًا لتواصلكم، أرفقنا الإرشادات.')
    await wrapper.find('form.contact-composer').trigger('submit')
    await flushPromises()
    expect(contactInboxService.reply).toHaveBeenCalledWith(ID, {
      body: 'شكرًا لتواصلكم، أرفقنا الإرشادات.',
    })
    expect(wrapper.find('.contact-controls .contact-status').text()).toBe('تم الرد')
    await chooseNamedOption(wrapper, 'assigneeId', '31')
    await wrapper.find('.contact-assign .btn').trigger('click')
    await flushPromises()
    expect(contactInboxService.assign).toHaveBeenCalledWith(ID, { assigneeId: 31 })
    const close = wrapper.findAll('button').find((button) => button.text() === 'إغلاق الرسالة')
    await close.trigger('click')
    await flushPromises()
    expect(contactInboxService.close).toHaveBeenCalledWith(ID, {})
    const closed = wrapper.find('.contact-panel__closed')
    expect(closed.text()).toContain('الرسالة مغلقة')
    expect(closed.text()).toContain('أغلقها أ. هيفاء العنزي')
    expect(closed.text()).toContain('إعادة فتح الرسالة')
    expect(wrapper.find('.contact-composer').exists()).toBe(false)
    expect(wrapper.find('textarea').exists()).toBe(false)
    expect(wrapper.vm.$router.currentRoute.value.params.id).toBe(ID)
    expect(contactInboxService.list.mock.calls.length).toBeGreaterThan(1)
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
    const wrapper = await mountAt(Inbox, THREAD)
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
    const wrapper = await mountAt(Inbox, THREAD, { superAdmin: true })
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
    const wrapper = await mountAt(Inbox, THREAD, { locale: 'en' })
    expect(wrapper.text()).toContain('Message not found')
    wrapper.unmount()
  })
})
