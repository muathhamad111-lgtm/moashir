import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import GapGuidanceEditor from '@/components/bag/GapGuidanceEditor.vue'
import { createTestGlobals } from '@/test-utils'
import { chooseNamedOption } from '@/test-utils/dropdown'

function localized(content) {
  if (!content) return ''
  return typeof content === 'string' ? content : content.en || content.ar || ''
}

const gap = {
  id: 'gap-503',
  gap: 100,
  target: 250,
  priority: 'high',
  status: 'approved',
  consultantNote: { ar: 'ملاحظة', en: 'Start with digital.' },
  recommendedAction: null,
}

function mountEditor(overrides = {}) {
  return mount(GapGuidanceEditor, {
    props: { gap: { ...gap, ...overrides }, saving: false, localized },
    global: createTestGlobals({ locale: 'en' }),
  })
}

describe('GapGuidanceEditor', () => {
  it('seeds the form from the gap and emits a bilingual save payload', async () => {
    const wrapper = mountEditor()
    await wrapper.find('form').trigger('submit.prevent')

    const payload = wrapper.emitted('save')?.[0]?.[0]
    expect(payload.priority).toBe('high')
    expect(payload.status).toBe('approved')
    expect(payload.consultantNote).toEqual({ ar: 'Start with digital.', en: 'Start with digital.' })
    expect(payload.recommendedAction).toBeNull()
  })

  it('emits the updated priority after the consultant changes it', async () => {
    const wrapper = mountEditor()
    await chooseNamedOption(wrapper, `gap-priority-${gap.id}`, 'low')
    await wrapper.find('form').trigger('submit.prevent')

    const payload = wrapper.emitted('save')?.[0]?.[0]
    expect(payload.priority).toBe('low')
  })

  it('disables the fields while a save is in flight', () => {
    const wrapper = mount(GapGuidanceEditor, {
      props: { gap, saving: true, localized },
      global: createTestGlobals({ locale: 'en' }),
    })
    expect(
      wrapper.find(`button[name="gap-priority-${gap.id}"]`).attributes('disabled'),
    ).toBeDefined()
  })
})
