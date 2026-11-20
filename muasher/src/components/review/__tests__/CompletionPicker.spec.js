import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import CompletionItemPicker from '@/components/review/CompletionItemPicker.vue'
import CompletionSelectionSummary from '@/components/review/CompletionSelectionSummary.vue'

const SUB_A = { id: 11, name: { ar: 'التوجه الاستراتيجي', en: 'Direction' } }
const SUB_B = { id: 12, name: { ar: 'الحوكمة', en: 'Governance' } }
const SUB_C = { id: 21, name: { ar: 'تحليل البيئة', en: 'Context' } }
const GROUPS = [
  {
    main: { id: 1, name: { ar: 'القيادة', en: 'Leadership' }, subs: [SUB_A, SUB_B] },
    options: [
      { key: '11:cv', sub: SUB_A, kind: 'cv' },
      { key: '11:evidence', sub: SUB_A, kind: 'evidence' },
      { key: '12:evidence', sub: SUB_B, kind: 'evidence' },
    ],
  },
  {
    main: { id: 2, name: { ar: 'التخطيط', en: 'Planning' }, subs: [SUB_C] },
    options: [{ key: '21:evidence', sub: SUB_C, kind: 'evidence' }],
  },
]

function picker(props = {}, locale = 'ar') {
  return mount(CompletionItemPicker, {
    props: { groups: GROUPS, selected: [], ...props },
    global: createTestGlobals({ locale }),
  })
}

describe('CompletionItemPicker', () => {
  it("offers the main criterion's own attachments as an evidence-only first row", async () => {
    const main = { id: 1, name: { ar: 'القيادة', en: 'Leadership' }, subs: [SUB_A] }
    const mainOption = { key: 'main-1:evidence', main, sub: null, kind: 'evidence' }
    const wrapper = picker(
      { groups: [{ main, options: [mainOption, { key: '11:cv', sub: SUB_A, kind: 'cv' }] }] },
      'en',
    )
    const rows = wrapper.findAll('.completion-picker__row')
    expect(rows.map((row) => row.find('th').text())).toEqual([
      'Main criterion evidence',
      'Direction',
    ])
    expect(rows[0].findAll('input')).toHaveLength(1)
    await wrapper.find('input[name="item-main-1:evidence"]').setValue(true)
    expect(wrapper.emitted('toggle')[0]).toEqual(['main-1:evidence', true])
  })

  it('opens the current criterion only, with a count per group and a dash where no CV exists', () => {
    const wrapper = picker({ openMainId: 2, selected: ['21:evidence'] })
    expect(wrapper.findAll('.completion-picker__row').map((row) => row.find('th').text())).toEqual([
      'تحليل البيئة',
    ])
    expect(wrapper.findAll('.completion-picker__count').map((count) => count.text())).toEqual([
      '0 من 3',
      '1 من 1',
    ])
    expect(wrapper.find('.completion-picker__none').exists()).toBe(true)
  })

  it('opens and closes a group, and a search shows every matching sub-criterion', async () => {
    const wrapper = picker()
    expect(wrapper.findAll('.completion-picker__row')).toHaveLength(2)
    await wrapper.findAll('.completion-picker__toggle')[0].trigger('click')
    expect(wrapper.findAll('.completion-picker__row')).toHaveLength(0)
    await wrapper.find('input[name="completion-search"]').setValue('الحوكمه')
    expect(wrapper.findAll('.completion-picker__row').map((row) => row.find('th').text())).toEqual([
      'الحوكمة',
    ])
    await wrapper.find('input[name="completion-search"]').setValue('xyz')
    expect(wrapper.find('.completion-picker__empty').exists()).toBe(true)
  })

  it('emits a cell and a whole column of a group, with named controls', async () => {
    const wrapper = picker({}, 'en')
    const cell = wrapper.find('input[name="item-11:evidence"]')
    expect(cell.attributes('aria-label')).toBe('Direction — Attachments')
    await cell.setValue(true)
    expect(wrapper.emitted('toggle')[0]).toEqual(['11:evidence', true])
    const column = wrapper.findAll('.completion-picker__group input')[1]
    await column.setValue(true)
    expect(wrapper.emitted('toggleMany')[0]).toEqual([['11:evidence', '12:evidence'], true])
  })
})

describe('CompletionSelectionSummary', () => {
  function summary(options) {
    return mount(CompletionSelectionSummary, {
      props: { options, notes: {} },
      global: createTestGlobals(),
    })
  }

  it('lists the chosen items as removable chips', async () => {
    const wrapper = summary(GROUPS[0].options.slice(0, 2))
    expect(wrapper.find('.completion-summary__title').text()).toBe('البنود المختارة (2)')
    expect(wrapper.findAll('.completion-summary__label').map((label) => label.text())).toEqual([
      'التوجه الاستراتيجي — السيرة',
      'التوجه الاستراتيجي — المرفقات',
    ])
    await wrapper.find('.completion-summary__remove').trigger('click')
    expect(wrapper.emitted('remove')[0]).toEqual(['11:cv'])
  })

  it('shows a note field only after «إضافة ملاحظة», or when the item already has a note', async () => {
    const wrapper = mount(CompletionSelectionSummary, {
      props: { options: GROUPS[0].options.slice(0, 2), notes: { '11:evidence': 'نسخة محدثة' } },
      global: createTestGlobals(),
    })
    expect(wrapper.find('input[name="note-11:cv"]').exists()).toBe(false)
    expect(wrapper.find('input[name="note-11:evidence"]').exists()).toBe(true)
    const add = wrapper.find('.completion-summary__add-note')
    expect(add.text()).toBe('إضافة ملاحظة')
    expect(add.attributes('aria-label')).toBe('إضافة ملاحظة على التوجه الاستراتيجي — السيرة')
    await add.trigger('click')
    await wrapper.find('input[name="note-11:cv"]').setValue('أرفق القوائم')
    expect(wrapper.emitted('note')[0]).toEqual(['11:cv', 'أرفق القوائم'])
  })

  it('invites a choice when nothing is selected', () => {
    expect(summary([]).find('.completion-summary__empty').exists()).toBe(true)
  })
})
