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
    listForRequest: vi.fn(),
    revoke: vi.fn(),
    reissue: vi.fn(),
    issueForRequest: vi.fn(),
    download: vi.fn(),
  },
}))

const ISSUED = {
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
  ...ISSUED,
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

beforeEach(() => {
  vi.clearAllMocks()
  certificatesService.listForRequest.mockResolvedValue({ data: [ISSUED, SUPERSEDED] })
})

describe('staff certificate card on the request detail', () => {
  it('shows the current certificate, its history and the manager actions', async () => {
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
    certificatesService.revoke.mockResolvedValue({ data: { ...ISSUED, status: 'revoked' } })
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

  it('lets the completing assessor issue the certificate without listing', async () => {
    certificatesService.issueForRequest.mockResolvedValue({ data: ISSUED })
    const wrapper = mountCard(['assessor'], {}, 'en')
    await flushPromises()
    expect(certificatesService.listForRequest).not.toHaveBeenCalled()
    const issue = wrapper
      .findAll('button')
      .find((button) => button.text() === 'Issue the certificate')
    await issue.trigger('click')
    await flushPromises()
    expect(certificatesService.issueForRequest).toHaveBeenCalledWith(9104, {})
    expect(wrapper.find('[data-certificate="current"]').text()).toContain('CRT-2026-000003')
  })

  it('explains that the certificate comes after completion', async () => {
    const wrapper = mountCard(['assessor'], { completed: false }, 'en')
    await flushPromises()
    expect(wrapper.text()).toContain('once the assessor approves the result')
    expect(wrapper.findAll('button')).toHaveLength(0)
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

  it('notes a missing assessor summary', async () => {
    const wrapper = mount(CompleteReviewDialog, {
      props: { modelValue: true, message: 'Complete?', summaryMissing: true },
      global: createTestGlobals({ locale: 'en' }),
      attachTo: document.body,
    })
    await flushPromises()
    expect(document.querySelector('[data-testid="summary-missing-hint"]')).not.toBeNull()
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
