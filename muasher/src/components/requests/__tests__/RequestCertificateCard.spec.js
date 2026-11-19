import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import RequestCertificateCard from '@/components/requests/RequestCertificateCard.vue'
import CompleteReviewDialog from '@/components/review/CompleteReviewDialog.vue'
import RequestQueueTable from '@/components/requests/RequestQueueTable.vue'
import { certificatesService } from '@/services/certificatesService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/certificatesService', () => ({
  certificatesService: {
    requestState: vi.fn(),
    listForRequest: vi.fn(),
    revoke: vi.fn(),
    reissue: vi.fn(),
    issueForRequest: vi.fn(),
    download: vi.fn(),
  },
}))

const ISSUED = {
  id: 'c1',
  number: 'CRT-2026-000041',
  status: 'issued',
  level: 'silver',
  issuedAt: '2026-11-19T10:00:00Z',
  hasPdf: true,
  issuedBy: { id: 10, name: 'د. خالد' },
}

async function mountAs(roles, { completed = true } = {}) {
  const globals = createTestGlobals({ locale: 'ar' })
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles))
  const wrapper = mount(RequestCertificateCard, {
    props: { requestId: 9101, completed },
    global: { ...globals, stubs: { teleport: true } },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => vi.clearAllMocks())

describe('RequestCertificateCard issuance mode', () => {
  it('offers the issue button to the assessor only in manual mode', async () => {
    certificatesService.requestState.mockResolvedValue({
      data: { certificate: null, issuanceMode: 'manual' },
    })
    const wrapper = await mountAs(['assessor'])
    expect(certificatesService.requestState).toHaveBeenCalledWith(9101)
    expect(wrapper.find('.request-certificate__issue').exists()).toBe(true)
    expect(wrapper.text()).toContain('أصدر الشهادة لتظهر للجهة')
  })

  it('shows no issue button in automatic mode and says the certificate is on its way', async () => {
    certificatesService.requestState.mockResolvedValue({
      data: { certificate: null, issuanceMode: 'automatic' },
    })
    const wrapper = await mountAs(['assessor'])
    expect(wrapper.find('.request-certificate__issue').exists()).toBe(false)
    expect(wrapper.text()).toContain('تصدر الشهادة تلقائيًا')
  })

  it('shows the issued certificate to the assessor in automatic mode, without an issue button', async () => {
    certificatesService.requestState.mockResolvedValue({
      data: { certificate: ISSUED, issuanceMode: 'automatic' },
    })
    const wrapper = await mountAs(['assessor'])
    expect(wrapper.find('[data-certificate="current"]').text()).toContain('CRT-2026-000041')
    expect(wrapper.find('.request-certificate__issue').exists()).toBe(false)
  })
})

const MANAGED_ISSUED = {
  id: 'c3',
  number: 'CRT-2026-000003',
  status: 'issued',
  levelLabel: { ar: 'المستوى الذهبي', en: 'Gold level' },
  issuedAt: '2026-06-22T09:00:00+00:00',
  issuedBy: { id: 10, name: 'د. خالد بن سعد القحطاني' },
  verificationCode: 'K7Q2-M9XA',
  hasPdf: true,
  revokedReason: null,
}
const SUPERSEDED = {
  ...MANAGED_ISSUED,
  id: 'c2',
  number: 'CRT-2026-000002',
  status: 'superseded',
  supersededAt: '2026-06-22T09:00:00+00:00',
}

function mountCard(roles, props = {}, locale = 'ar') {
  const globals = createTestGlobals({ locale })
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles))
  return mount(RequestCertificateCard, {
    props: { requestId: 9104, completed: true, ...props },
    global: globals,
  })
}

function listCertificates(data) {
  certificatesService.listForRequest.mockResolvedValue({ data, issuanceMode: 'manual' })
}

describe('staff certificate card on the request detail', () => {
  it('shows the current certificate, its history and the manager actions', async () => {
    listCertificates([MANAGED_ISSUED, SUPERSEDED])
    const wrapper = mountCard(['system_admin'])
    await flushPromises()
    expect(certificatesService.listForRequest).toHaveBeenCalledWith(9104)
    const current = wrapper.find('[data-certificate="current"]')
    expect(current.text()).toContain('CRT-2026-000003')
    expect(current.text()).toContain('المستوى الذهبي')
    expect(current.text()).toContain('د. خالد بن سعد القحطاني')
    const labels = current.findAll('button').map((button) => button.text())
    expect(labels).toEqual(['تنزيل الشهادة PDF', 'إعادة الإصدار', 'إلغاء الشهادة'])
    expect(wrapper.find('.request-certificate__history').text()).toContain('CRT-2026-000002')
    expect(wrapper.text()).toContain('التقرير التعقيبي للطلب')
  })

  it('revokes with the typed reason and reloads the list', async () => {
    listCertificates([MANAGED_ISSUED, SUPERSEDED])
    certificatesService.revoke.mockResolvedValue({ data: { ...MANAGED_ISSUED, status: 'revoked' } })
    const wrapper = mountCard(['system_admin'])
    await flushPromises()
    const revoke = wrapper.findAll('button').find((button) => button.text() === 'إلغاء الشهادة')
    await revoke.trigger('click')
    await flushPromises()
    const dialog = wrapper.findAllComponents({ name: 'ReasonDialog' })[0]
    await dialog.props('action')('أُلغيت لخطأ موثق في احتساب النتيجة')
    await flushPromises()
    expect(certificatesService.revoke).toHaveBeenCalledWith(
      'c3',
      'أُلغيت لخطأ موثق في احتساب النتيجة',
    )
    expect(certificatesService.listForRequest).toHaveBeenCalledTimes(2)
  })

  it('hides the card before completion while the request has no certificate', async () => {
    listCertificates([])
    certificatesService.requestState.mockResolvedValue({
      data: { certificate: null, issuanceMode: 'manual' },
    })
    const assessor = mountCard(['assessor'], { completed: false }, 'en')
    const follower = mountCard(['request_follower'], { completed: false }, 'en')
    await flushPromises()
    expect(assessor.find('.request-certificate').exists()).toBe(false)
    expect(follower.find('.request-certificate').exists()).toBe(false)
  })

  it('keeps the history of a reopened request and explains the certificate comes after completion', async () => {
    listCertificates([
      { ...MANAGED_ISSUED, status: 'revoked', revokedAt: '2026-06-23T09:00:00+00:00' },
    ])
    const wrapper = mountCard(['system_admin'], { completed: false }, 'en')
    await flushPromises()
    expect(wrapper.text()).toContain('once the assessor approves the result')
    expect(wrapper.find('.request-certificate__history').text()).toContain('CRT-2026-000003')
  })
})

describe('complete dialog of the assessor review', () => {
  it('asks one question with no certificate choice, since issuance is automatic', async () => {
    const wrapper = mount(CompleteReviewDialog, {
      props: {
        modelValue: true,
        message: 'The result is published and the certificate is issued automatically.',
      },
      global: createTestGlobals({ locale: 'en' }),
      attachTo: document.body,
    })
    await flushPromises()
    expect(document.querySelector('input[type="checkbox"]')).toBeNull()
    expect(document.querySelector('[role="alertdialog"] .modal__message').textContent).toContain(
      'issued automatically',
    )
    const confirm = document.querySelector('.modal__footer .btn--primary')
    expect(confirm.textContent.trim()).toBe('Complete')
    confirm.click()
    await flushPromises()
    expect(wrapper.emitted('confirm')).toHaveLength(1)
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([false])
    wrapper.unmount()
  })
})

describe('request queue region column', () => {
  it('shows the region only where the API fills it', () => {
    const globals = createTestGlobals({ locale: 'en' })
    const headers = (showsRegion) =>
      mount(RequestQueueTable, {
        props: { items: [], status: 'success', showsRegion },
        global: globals,
      })
        .findAll('th')
        .map((th) => th.text())
    expect(headers(false)).not.toContain('Region')
    expect(headers(true)).toContain('Region')
  })
})
