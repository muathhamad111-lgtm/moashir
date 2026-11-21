import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import SegmentPicker from '@/components/messaging/SegmentPicker.vue'
import { createTestGlobals } from '@/test-utils'
import { REMOTE_SEARCH_DELAY_MS } from '@/constants/dropdown'

const segments = [
  { key: 'qualified', label: { ar: 'المتأهلة', en: 'Qualified' }, count: 12 },
  { key: 'all', label: { ar: 'الكل', en: 'All' }, count: 118 },
  { key: 'individual', label: { ar: 'جهة محددة', en: 'Individual' }, count: 0 },
]

const recipients = [
  { id: 1, name: { ar: 'جمعية البر', en: 'Al-Birr' }, email: 'a@x.example.sa' },
  { id: 2, name: { ar: 'مؤسسة إنسان', en: 'Insan' }, email: 'b@x.example.sa' },
]

function mountPicker(props = {}) {
  return mount(SegmentPicker, {
    props: {
      modelValue: { segment: '', recipientEntityId: null },
      segments,
      recipients,
      ...props,
    },
    global: createTestGlobals({ locale: 'en' }),
  })
}

describe('SegmentPicker', () => {
  it('renders one radio per segment with its recipient count', () => {
    const wrapper = mountPicker()
    const options = wrapper.findAll('.segment-picker__option')
    expect(options).toHaveLength(3)
    expect(wrapper.findAll('.segment-picker__radio')).toHaveLength(3)
    expect(wrapper.text()).toContain('118')
  })

  it('emits the chosen segment and clears any recipient when a non-individual is picked', async () => {
    const wrapper = mountPicker({ modelValue: { segment: 'individual', recipientEntityId: 2 } })
    const radios = wrapper.findAll('.segment-picker__radio')
    await radios[0].setValue(true)
    expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toEqual({
      segment: 'qualified',
      recipientEntityId: null,
    })
  })

  it('shows the individual recipient picker only when "individual" is selected', async () => {
    const wrapper = mountPicker()
    expect(wrapper.find('.segment-picker__individual').exists()).toBe(false)
    await wrapper.setProps({ modelValue: { segment: 'individual', recipientEntityId: null } })
    expect(wrapper.find('.segment-picker__individual').exists()).toBe(true)
  })

  it('bubbles a recipient search event from the individual picker', async () => {
    vi.useFakeTimers()
    const wrapper = mountPicker({ modelValue: { segment: 'individual', recipientEntityId: null } })
    await wrapper.find('.dropdown-combobox__input').setValue('بر')
    expect(wrapper.emitted('search-recipients')).toBeUndefined()
    vi.advanceTimersByTime(REMOTE_SEARCH_DELAY_MS)
    expect(wrapper.emitted('search-recipients')?.at(-1)).toEqual(['بر'])
    vi.useRealTimers()
  })
})
