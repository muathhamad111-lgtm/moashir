import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import ExecutiveFacts from '@/components/insights/ExecutiveFacts.vue'
import InsightsEntityLogo from '@/components/insights/InsightsEntityLogo.vue'
import InsightsResultSelector from '@/components/insights/InsightsResultSelector.vue'
import ReportLibraryRow from '@/components/insights/ReportLibraryRow.vue'
import AppSelect from '@/components/form/AppSelect.vue'

const ISSUED = { id: 'c1', number: 'CRT-2026-000031', status: 'issued', level: 'gold' }
const RESULT = {
  assessmentId: 14,
  number: 'ACC-2026-000014',
  verifiedAt: '2026-10-28T08:00:00Z',
  certificate: ISSUED,
}
const PDF_URL = 'http://localhost/api/v1/assessments/14/feedback-report/pdf?signature=abc'

function render(component, props, locale = 'ar') {
  return mount(component, { props, global: createTestGlobals({ locale }) })
}

function facts(certificate, standing = { standing: 'level', level: 'gold' }) {
  return render(ExecutiveFacts, {
    summary: {
      result: { ...RESULT, certificate },
      score: { percent: '82.00', earned: '229.60', totalWeight: '280.00' },
      certificate: standing,
      change: { deltaPp: null, previous: null },
    },
  })
}

function libraryRow(item, certificate = ISSUED, locale = 'ar') {
  return render(
    ReportLibraryRow,
    { item, assessmentId: 14, result: { ...RESULT, certificate } },
    locale,
  )
}

describe('executive summary — entity logo', () => {
  it('renders the image from the signed logo link', () => {
    const wrapper = render(InsightsEntityLogo, { url: 'blob:logo', name: 'جمعية الأمل' })
    const image = wrapper.find('img')
    expect(image.attributes('src')).toBe('blob:logo')
    expect(image.attributes('alt')).toBe('شعار جمعية الأمل')
  })

  it('shows the placeholder without a link, even when the entity has a logo', () => {
    const wrapper = render(InsightsEntityLogo, { url: null, name: 'Al-Amal' }, 'en')
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('svg').exists()).toBe(true)
  })

  it('falls back to the placeholder when the link fails', async () => {
    const wrapper = render(InsightsEntityLogo, { url: 'blob:expired', name: 'Al-Amal' })
    await wrapper.find('img').trigger('error')
    expect(wrapper.find('img').exists()).toBe(false)
  })
})

describe('certificate number', () => {
  it('prints the valid certificate number under the level', () => {
    const number = facts(ISSUED).find('[data-testid="executive-certificate-number"]')
    expect(number.text()).toBe('شهادة سارية CRT-2026-000031')
    expect(number.find('bdi').attributes('dir')).toBe('ltr')
  })

  it('never prints a revoked or suspended certificate as valid', () => {
    for (const status of ['revoked', 'suspended']) {
      const wrapper = facts({ ...ISSUED, status }, { standing: 'not_issued', level: null })
      expect(wrapper.find('[data-testid="executive-certificate-number"]').exists()).toBe(false)
    }
  })

  it('labels a participation certificate', () => {
    const wrapper = facts({ ...ISSUED, level: null }, { standing: 'participation', level: null })
    expect(wrapper.find('[data-testid="executive-certificate-number"]').text()).toBe(
      'شهادة مشاركة CRT-2026-000031',
    )
  })

  it('adds the issued certificate number to the selector option only', () => {
    const wrapper = render(InsightsResultSelector, {
      results: [
        RESULT,
        { ...RESULT, assessmentId: 9, certificate: { ...ISSUED, status: 'revoked' } },
      ],
      modelValue: 14,
    })
    const labels = wrapper
      .findComponent(AppSelect)
      .props('options')
      .map((option) => option.label)
    expect(labels[0]).toBe('ACC-2026-000014 · 2026-10-28 · CRT-2026-000031 · الأحدث')
    expect(labels[1]).toBe('ACC-2026-000014 · 2026-10-28')
  })
})

describe('report library row', () => {
  const certificateItem = { kind: 'certificate', available: true, status: 'issued' }

  it('shows the number and level name of a valid certificate', () => {
    const wrapper = libraryRow(certificateItem)
    expect(wrapper.find('[data-testid="library-certificate-number"]').text()).toBe(
      'CRT-2026-000031',
    )
    expect(wrapper.text()).toContain('المستوى الذهبي')
    expect(wrapper.find('.library-row__status').text()).toBe('سارية')
  })

  it('shows a revoked certificate number with its status chip and no level', () => {
    const wrapper = libraryRow(
      { kind: 'certificate', available: false, status: 'revoked' },
      { ...ISSUED, status: 'revoked' },
    )
    expect(wrapper.find('[data-testid="library-certificate-number"]').text()).toBe(
      'CRT-2026-000031',
    )
    expect(wrapper.text()).not.toContain('المستوى الذهبي')
    expect(wrapper.find('.library-row__status').text()).toBe('ملغاة')
  })

  it('offers the feedback report PDF only when the signed link is set', async () => {
    const withPdf = libraryRow(
      { kind: 'feedback_report', available: true, pdfAvailable: true, pdfUrl: PDF_URL },
      ISSUED,
      'en',
    )
    const button = withPdf.find('[data-testid="library-feedback-pdf"]')
    expect(button.attributes('aria-label')).toBe(
      'Download the feedback report PDF of result ACC-2026-000014',
    )
    await button.trigger('click')
    expect(withPdf.emitted('feedback-pdf')).toEqual([[PDF_URL]])
    expect(withPdf.find('.library-row__link').text()).toBe('View')

    const withoutLink = libraryRow({
      kind: 'feedback_report',
      available: true,
      pdfAvailable: true,
      pdfUrl: null,
    })
    expect(withoutLink.find('[data-testid="library-feedback-pdf"]').exists()).toBe(false)
    expect(withoutLink.find('.library-row__link').text()).toBe('عرض')
  })
})
