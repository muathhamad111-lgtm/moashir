import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import RichTextBlocks from '@/components/legal/RichTextBlocks.vue'
import { createTestGlobals } from '@/test-utils'

const BLOCKS = [
  { type: 'heading', level: 2, text: '1. مقدمة' },
  { type: 'paragraph', text: '<script>alert(1)</script> نص عادي' },
  { type: 'list', ordered: true, start: 3, items: ['أولاً', 'ثانياً'] },
  { type: 'list', ordered: false, start: null, items: ['بند'] },
  { type: 'table', header: ['الفئة', 'المدة'], rows: [['الرسائل', '24 شهراً']] },
  { type: 'unknown', text: 'لا يظهر' },
]

function mountBlocks(headingBase = 2) {
  return mount(RichTextBlocks, {
    props: { blocks: BLOCKS, headingBase },
    global: createTestGlobals(),
  })
}

describe('RichTextBlocks', () => {
  it('renders headings, paragraphs, lists and tables as text only', () => {
    const wrapper = mountBlocks()
    expect(wrapper.find('h2').text()).toBe('1. مقدمة')
    expect(wrapper.find('script').exists()).toBe(false)
    expect(wrapper.find('p').text()).toContain('<script>')
    expect(wrapper.find('ol').attributes('start')).toBe('3')
    expect(wrapper.findAll('ol li')).toHaveLength(2)
    expect(wrapper.findAll('ul li')).toHaveLength(1)
    expect(wrapper.findAll('th').map((cell) => cell.attributes('scope'))).toEqual(['col', 'col'])
    expect(wrapper.find('td').text()).toBe('الرسائل')
    expect(wrapper.text()).not.toContain('لا يظهر')
  })

  it('shifts the heading levels under a section title', () => {
    const wrapper = mountBlocks(3)
    expect(wrapper.find('h2').exists()).toBe(false)
    expect(wrapper.find('h3').text()).toBe('1. مقدمة')
  })
})
