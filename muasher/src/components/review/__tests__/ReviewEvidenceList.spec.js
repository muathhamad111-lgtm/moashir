import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import ReviewEvidenceList from '@/components/review/ReviewEvidenceList.vue'

const FILES = [
  { id: 1, fileName: 'vision-mission-2024.pdf', sizeBytes: 180224 },
  { id: 2, fileName: 'governance-charter.docx', sizeBytes: 90112 },
]

function render(props, locale = 'ar') {
  return mount(ReviewEvidenceList, {
    props: { files: FILES, downloading: {}, ...props },
    global: createTestGlobals({ locale }),
  })
}

describe('ReviewEvidenceList', () => {
  it('shows each file as a card with its type, full name tooltip and a labelled download button', () => {
    const wrapper = render()
    expect(wrapper.find('.review-evidence__title').text()).toBe('المرفقات (2)')
    const cards = wrapper.findAll('.review-evidence__card')
    expect(cards).toHaveLength(2)
    expect(cards.map((card) => card.find('.review-evidence__type').text())).toEqual(['PDF', 'DOCX'])
    expect(cards[0].attributes('title')).toBe('vision-mission-2024.pdf')
    const button = cards[0].find('button')
    expect(button.text()).toBe('تنزيل')
    expect(button.attributes('aria-label')).toContain('vision-mission-2024.pdf')
  })

  it('emits the file to download and speaks English in English', async () => {
    const wrapper = render({}, 'en')
    await wrapper.findAll('button')[1].trigger('click')
    expect(wrapper.emitted('download')[0][0]).toEqual(FILES[1])
    expect(wrapper.find('button').text()).toBe('Download')
    expect(wrapper.find('.review-evidence__title').text()).toBe('Attachments (2)')
  })

  it('says when there is no clean evidence', () => {
    const wrapper = render({ files: [] })
    expect(wrapper.find('.review-evidence__card').exists()).toBe(false)
    expect(wrapper.find('.review-evidence__title').text()).toBe('المرفقات (0)')
    expect(wrapper.find('.review-evidence__empty').text()).toBe(
      'لا توجد مرفقات لهذا المعيار الفرعي.',
    )
  })
})
