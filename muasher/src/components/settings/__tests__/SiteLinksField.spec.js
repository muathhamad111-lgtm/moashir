import { describe, expect, it } from 'vitest'
import { nextTick, ref } from 'vue'
import { mount } from '@vue/test-utils'
import SiteLinksField from '@/components/settings/SiteLinksField.vue'
import { createTestGlobals } from '@/test-utils'

function linkRow(label) {
  return { label: { ar: label, en: label }, url: `https://example.sa/${label}` }
}

function mountField(rows) {
  return mount(SiteLinksField, {
    props: {
      rows: rows.value,
      'onUpdate:rows': (next) => {
        rows.value = next
      },
      field: 'headerLinks',
      legend: 'Header links',
      max: 5,
      addLabel: 'Add link',
      emptyText: 'No links',
      errorOf: () => '',
    },
    global: createTestGlobals({ locale: 'en' }),
  })
}

describe('SiteLinksField', () => {
  it('moves the row element with its link when the order changes', async () => {
    const rows = ref([linkRow('first'), linkRow('second')])
    const wrapper = mountField(rows)
    const secondRow = wrapper.findAll('.site-settings__row--stack')[1].element
    const moveUp = wrapper.findAll('.site-settings__row-actions button')[3]
    await moveUp.trigger('click')
    await nextTick()
    expect(rows.value[0].label.en).toBe('second')
    expect(wrapper.findAll('.site-settings__row--stack')[0].element).toBe(secondRow)
  })

  it('keeps the remaining row element when an earlier row is removed', async () => {
    const rows = ref([linkRow('first'), linkRow('second')])
    const wrapper = mountField(rows)
    const secondRow = wrapper.findAll('.site-settings__row--stack')[1].element
    await wrapper.findAll('.site-settings__row-actions button')[2].trigger('click')
    await nextTick()
    expect(rows.value).toHaveLength(1)
    expect(wrapper.find('.site-settings__row--stack').element).toBe(secondRow)
  })
})
