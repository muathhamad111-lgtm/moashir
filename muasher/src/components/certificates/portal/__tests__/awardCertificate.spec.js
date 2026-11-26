import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CertificateCard from '@/components/certificates/portal/CertificateCard.vue'
import VerificationResult from '@/components/verify/VerificationResult.vue'
import { toCertificateCard, toVerification } from '@/services/mappers/certificates'
import { createTestGlobals } from '@/test-utils'

const AWARD = {
  cycleName: { ar: 'دورة جائزة مؤشر للتميز المؤسسي', en: 'Moashir Institutional Excellence Award' },
  cycleYear: 2026,
  region: { ar: 'منطقة الرياض', en: 'Riyadh Region' },
  regionalRank: 2,
  nationalRank: 1,
}

const WINNER = toCertificateCard({
  id: 'a1',
  number: 'CRT-2026-000040',
  kind: 'advanced_winner',
  level: null,
  levelLabel: null,
  status: 'issued',
  percent: null,
  award: AWARD,
  request: { id: 9204, number: 'ADV-2026-000004' },
  entity: { name: { ar: 'مؤسسة رواد التعليم', en: 'Ruwad Education Foundation' } },
  verificationCode: 'P7RW-3KDM-9XTQ-H2VN-6ZCA-4BGE-8JFY',
  issuedAt: '2026-12-01T08:00:00Z',
})

describe('award certificates', () => {
  it('titles the card by its kind and lists the cycle, region and ranks without a score', () => {
    const wrapper = mount(CertificateCard, {
      props: { certificate: WINNER },
      global: createTestGlobals(),
    })
    expect(wrapper.find('.certificate-card__title').text()).toBe('شهادة الفوز بالجائزة')
    const facts = wrapper.find('.certificate-card__facts').text()
    expect(facts).toContain('دورة جائزة مؤشر للتميز المؤسسي 2026')
    expect(facts).toContain('منطقة الرياض')
    expect(facts).toContain('الثاني')
    expect(facts).toContain('الأول')
    expect(facts).not.toContain('%')
  })

  it('shows only the cycle on a participation certificate', () => {
    const participation = toCertificateCard({
      ...WINNER,
      kind: 'advanced_participation',
      award: { ...AWARD, region: null, regionalRank: null, nationalRank: null },
    })
    const wrapper = mount(CertificateCard, {
      props: { certificate: participation },
      global: createTestGlobals(),
    })
    expect(wrapper.find('.certificate-card__title').text()).toBe('شهادة المشاركة في الجائزة')
    expect(wrapper.find('.certificate-card__facts').text()).not.toContain('المركز')
  })

  it('verifies an award certificate publicly with its kind and ranks, never a score', () => {
    const verification = toVerification({
      result: 'valid',
      certificate: {
        number: 'CRT-2026-000040',
        kind: 'advanced_winner',
        level: null,
        percent: null,
        award: AWARD,
        entityName: { ar: 'مؤسسة رواد التعليم', en: 'Ruwad' },
        issuedAt: '2026-12-01T08:00:00Z',
      },
    })
    const wrapper = mount(VerificationResult, {
      props: { verification, code: 'P7RW-3KDM' },
      global: createTestGlobals(),
    })
    const text = wrapper.find('.verification-result__facts').text()
    expect(text).toContain('شهادة الفوز بالجائزة')
    expect(text).toContain('المركز على مستوى المملكة')
    expect(text).not.toContain('النتيجة')
    expect(text).not.toContain('%')
  })
})
