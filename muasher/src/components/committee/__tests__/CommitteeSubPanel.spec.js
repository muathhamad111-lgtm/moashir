import { describe, expect, it } from 'vitest'
import { computed, defineComponent, h, reactive, ref, shallowRef } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import CommitteeSubPanel from '@/components/committee/CommitteeSubPanel.vue'
import { useCommitteeNotes } from '@/composables/useCommitteeNotes'
import { useSelfScoreSheet } from '@/composables/useSelfScoreSheet'
import { scorableUnits } from '@/utils/assessments/scoreSheet'
import { createTestGlobals } from '@/test-utils'
import {
  ACHIEVEMENT_SUB,
  RELATIVE_SUB,
  SCORE_SUB,
  committeeEvaluationOf,
} from '@/components/committee/__tests__/committeeEvaluationFixture'

const OF_10 = { kind: 'of_10', maximum: '10.00' }
const ACHIEVEMENT_ERROR = 'اختر متحقق أو غير متحقق.'

function mountPanel(sub, { scale = null, subs = null } = {}) {
  let scoreSheet
  const Host = defineComponent({
    setup() {
      const evaluation = shallowRef(committeeEvaluationOf({ scale, subs }))
      const units = computed(() => scorableUnits(evaluation.value.criteria))
      scoreSheet = useSelfScoreSheet({ form: evaluation, units })
      const workspace = {
        state: { evaluation, units, canScore: ref(true) },
        scoreSheet,
        notes: useCommitteeNotes(),
        actions: { busy: ref('') },
        downloads: { busy: reactive({}) },
      }
      return () => h(CommitteeSubPanel, { sub, workspace })
    },
  })
  const wrapper = mount(Host, { global: { plugins: createTestGlobals({ locale: 'ar' }).plugins } })
  return { wrapper, sheet: () => scoreSheet }
}

describe('CommitteeSubPanel', () => {
  it('renders the review achieved / not achieved control for an achievement item when the request has no scale stamp', async () => {
    const { wrapper } = mountPanel(ACHIEVEMENT_SUB)
    await flushPromises()
    const field = wrapper.find('.review-achievement')
    expect(field.exists()).toBe(true)
    expect(field.findAll('[role="radio"]').map((choice) => choice.text())).toEqual([
      'متحقق',
      'غير متحقق',
    ])
    expect(wrapper.find('input[inputmode="decimal"]').exists()).toBe(false)
    expect(wrapper.find('.achievement-score').exists()).toBe(false)
  })

  it('passes validation with the achieved value on an unstamped request', async () => {
    const { wrapper, sheet } = mountPanel(ACHIEVEMENT_SUB)
    await flushPromises()
    await wrapper.findAll('.review-achievement [role="radio"]')[0].trigger('click')
    await flushPromises()
    expect(sheet().sheet.values['sub-103']).toBe('1')
    expect(await sheet().isValid()).toBe(true)
    await flushPromises()
    expect(wrapper.text()).not.toContain(ACHIEVEMENT_ERROR)
    expect(sheet().payload().subs).toMatchObject({ 103: '1' })
  })

  it('keeps the numeric stepper for a score item on an unstamped request', async () => {
    const { wrapper, sheet } = mountPanel(SCORE_SUB)
    await flushPromises()
    expect(wrapper.find('.review-achievement').exists()).toBe(false)
    const input = wrapper.find('.review-score input[inputmode="decimal"]')
    expect(input.exists()).toBe(true)
    await input.setValue('8')
    await flushPromises()
    expect(await sheet().isValid()).toBe(true)
  })

  it('lets the request scale stamp win over the node type, as the server does', async () => {
    const { wrapper, sheet } = mountPanel(ACHIEVEMENT_SUB, { scale: OF_10 })
    await flushPromises()
    expect(wrapper.find('.review-achievement').exists()).toBe(false)
    await wrapper.find('input[inputmode="decimal"]').setValue('8')
    await flushPromises()
    expect(await sheet().isValid()).toBe(true)
  })

  it('uses the review relative control (steps of 5) for a relative item', async () => {
    const { wrapper } = mountPanel(RELATIVE_SUB, { subs: [RELATIVE_SUB] })
    await flushPromises()
    const input = wrapper.find('.review-score input[inputmode="decimal"]')
    expect(input.exists()).toBe(true)
    expect(wrapper.find('.review-score__max').text()).toBe('%')
  })
})
