import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CriteriaPublishPreview from '@/components/criteria/CriteriaPublishPreview.vue'
import { createTestGlobals } from '@/test-utils'

const preview = {
  replaces: { id: 1, versionNumber: 3 },
  changeCount: 2,
  scoreAffectingCount: 1,
  changes: [
    {
      nodeType: 'sub',
      code: 'M1.1',
      change: 'modified',
      fields: ['weight', 'name'],
      before: { weight: '400.00' },
      after: { weight: '500.00' },
      scoreAffecting: true,
      label: { ar: 'السياسات', en: 'Policies' },
    },
    {
      nodeType: 'point',
      code: 'M1.2.3',
      change: 'added',
      fields: [],
      before: {},
      after: {},
      scoreAffecting: true,
      label: { ar: 'نقطة جديدة', en: 'New point' },
    },
  ],
  reboundOpenAssessments: [
    { scope: 'all', count: 4 },
    { scope: 'new_only', count: 0 },
    { scope: 'reset_open', count: 4 },
  ],
}

function mountPreview(props = {}, locale = 'en') {
  return mount(CriteriaPublishPreview, {
    props: { preview, ...props },
    global: createTestGlobals({ locale }),
  })
}

describe('CriteriaPublishPreview', () => {
  it('summarizes the changes and asks for a scope before stating the impact', () => {
    const wrapper = mountPreview()

    expect(wrapper.find('.criteria-publish-preview__summary').text()).toBe(
      '2 changes; 1 of them affect scores.',
    )
    expect(wrapper.find('.criteria-publish-preview__impact').text()).toContain(
      'Choose an edit scope',
    )
  })

  it('states how many open requests each scope moves', async () => {
    const wrapper = mountPreview({ scope: 'all' })
    expect(wrapper.find('.criteria-publish-preview__impact').text()).toContain(
      '4 open requests move to the new version; scores of unchanged items are kept.',
    )

    await wrapper.setProps({ scope: 'new_only' })
    expect(wrapper.find('.criteria-publish-preview__impact').text()).toContain(
      'Open requests stay on version 3',
    )

    await wrapper.setProps({ scope: 'reset_open' })
    expect(wrapper.find('.criteria-publish-preview__impact').text()).toContain(
      'all their scores are cleared',
    )
  })

  it('lists each change with its node, label, changed fields and weight', () => {
    const items = mountPreview().findAll('.criteria-change-list__item')

    expect(items).toHaveLength(2)
    expect(items[0].text()).toContain('Changed')
    expect(items[0].text()).toContain('Affects scores')
    expect(items[0].text()).toContain('Sub-criterion M1.1 — Policies')
    expect(items[0].text()).toContain('Changed: weight and name')
    expect(items[0].text()).toContain('400.00 → 500.00')
    expect(items[1].text()).toContain('Added')
    expect(items[1].text()).toContain('New point')
  })

  it('renders the Arabic texts', () => {
    const wrapper = mountPreview({ scope: 'all' }, 'ar')

    expect(wrapper.find('.criteria-publish-preview__title').text()).toBe('ما الذي سيتغير')
    expect(wrapper.find('.criteria-change-list__item').text()).toContain('معيار فرعي')
  })

  it('says when the draft matches the published version', () => {
    const wrapper = mountPreview({ preview: { ...preview, changeCount: 0, changes: [] } })

    expect(wrapper.find('.criteria-publish-preview__summary').text()).toBe(
      'No differences from version 3.',
    )
    expect(wrapper.find('.criteria-publish-preview__details').exists()).toBe(false)
  })

  it('offers a retry when the comparison fails', async () => {
    const wrapper = mountPreview({ preview: null, failed: true })

    await wrapper.find('button').trigger('click')

    expect(wrapper.emitted('retry')).toHaveLength(1)
  })
})
