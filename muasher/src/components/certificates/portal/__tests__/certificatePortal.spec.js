import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CertificateCard from '@/components/certificates/portal/CertificateCard.vue'
import VerificationResult from '@/components/verify/VerificationResult.vue'
import { createTestGlobals } from '@/test-utils'

const CERTIFICATE = {
  id: 'c1',
  number: 'CRT-2026-000003',
  level: 'silver',
  levelLabel: { ar: 'المستوى الفضي', en: 'Silver level' },
  status: 'issued',
  isTest: false,
  requestId: 14,
  requestNumber: 'ACC-2026-000014',
  entityName: { ar: 'مؤسسة نماء للتنمية', en: 'Namaa Development Foundation' },
  verificationCode: '9F3K-MQ2T-7XHD-M4PA-Z81C-QW5N-RT6B',
  verificationUrl: 'https://moashir.example.sa/verify/9F3K',
  issuedAt: '2026-09-05T08:00:00Z',
  revokedAt: null,
  revokedReason: null,
  supersededAt: null,
}

function mountCard(certificate = CERTIFICATE) {
  return mount(CertificateCard, { props: { certificate }, global: createTestGlobals() })
}

describe('CertificateCard', () => {
  it('shows the level title, facts, verification code and the download for a valid certificate', async () => {
    const wrapper = mountCard()
    expect(wrapper.find('.certificate-card__title').text()).toBe(
      'شهادة التميّز المؤسسي — المستوى الفضي',
    )
    expect(wrapper.find('.certificate-card__facts').text()).toContain('2026-09-05')
    expect(wrapper.find('.certificate-card__code-value').text()).toBe(CERTIFICATE.verificationCode)
    await wrapper.find('.certificate-card__actions .btn').trigger('click')
    expect(wrapper.emitted('download')[0][0].id).toBe('c1')
    await wrapper.find('.certificate-card__copy').trigger('click')
    expect(wrapper.emitted('copy')).toHaveLength(1)
    expect(wrapper.find('a[href^="/verify/"]').exists()).toBe(true)
  })

  it('disables the download of a suspended certificate and explains the review', () => {
    const wrapper = mountCard({ ...CERTIFICATE, status: 'suspended' })
    expect(wrapper.find('.certificate-card__actions .btn').attributes('disabled')).toBeDefined()
    expect(wrapper.find('.certificate-card__notice').text()).toContain('معلّقة مؤقتاً')
  })

  it('shows the revocation date and reason without a download', () => {
    const wrapper = mountCard({
      ...CERTIFICATE,
      status: 'revoked',
      revokedAt: '2026-10-01T08:00:00Z',
      revokedReason: 'خطأ في بيانات الجهة',
    })
    expect(wrapper.find('.certificate-card__actions .btn').exists()).toBe(false)
    expect(wrapper.find('.certificate-card__notice--muted').text()).toContain('2026-10-01')
    expect(wrapper.find('.certificate-card__reason').text()).toContain('خطأ في بيانات الجهة')
  })
})

describe('VerificationResult', () => {
  function mountResult(verification) {
    return mount(VerificationResult, {
      props: { verification, code: '9F3K-MQ2T' },
      global: createTestGlobals(),
    })
  }

  it('shows a valid certificate with its level and score', () => {
    const wrapper = mountResult({
      result: 'valid',
      certificate: {
        number: 'CRT-2026-000003',
        level: 'silver',
        levelLabel: { ar: 'المستوى الفضي', en: 'Silver level' },
        percent: '74.50',
        entityName: { ar: 'مؤسسة نماء للتنمية', en: 'Namaa' },
        issuedAt: '2026-09-05T08:00:00Z',
      },
    })
    expect(wrapper.classes()).toContain('verification-result--valid')
    expect(wrapper.text()).toContain('المستوى الفضي')
    expect(wrapper.text()).toContain('74.5%')
  })

  it.each([
    ['ar', 'غير صالحة للتحقق'],
    ['en', 'Not valid for verification'],
  ])('answers the unverifiable outcome without any certificate detail (%s)', (locale, title) => {
    const wrapper = mount(VerificationResult, {
      props: { verification: { result: 'unverifiable', certificate: null }, code: '9F3K-MQ2T' },
      global: createTestGlobals({ locale }),
    })
    expect(wrapper.classes()).toContain('verification-result--unverifiable')
    expect(wrapper.find('.verification-result__title').text()).toBe(title)
    expect(wrapper.find('.verification-result__card').exists()).toBe(false)
    expect(wrapper.text()).not.toMatch(/اختبار|تجريبي|\btest\b/i)
  })

  it('explains an expired certificate and still shows its card', () => {
    const wrapper = mountResult({
      result: 'expired',
      certificate: {
        number: 'CRT-2027-000001',
        level: 'gold',
        levelLabel: { ar: 'المستوى الذهبي', en: 'Gold level' },
        percent: '86.00',
        entityName: { ar: 'مؤسسة نماء للتنمية', en: 'Namaa' },
        issuedAt: '2027-01-10T08:00:00Z',
      },
    })
    expect(wrapper.classes()).toContain('verification-result--expired')
    expect(wrapper.find('.verification-result__title').text()).toBe('انتهت صلاحية الشهادة')
    expect(wrapper.find('.verification-result__icon').exists()).toBe(true)
    expect(wrapper.find('.verification-result__card').exists()).toBe(true)
  })

  it('hides the level of a certificate under review and handles an unknown code', () => {
    const review = mountResult({
      result: 'under_review',
      certificate: {
        number: 'CRT-1',
        level: null,
        levelLabel: null,
        percent: null,
        entityName: null,
      },
    })
    expect(review.classes()).toContain('verification-result--under-review')
    expect(review.text()).not.toContain('المستوى')
    const missing = mountResult({ result: 'not_found', certificate: null })
    expect(missing.find('.verification-result__card').exists()).toBe(false)
    expect(missing.find('.verification-result__title').text()).toContain('لم نعثر')
  })
})
