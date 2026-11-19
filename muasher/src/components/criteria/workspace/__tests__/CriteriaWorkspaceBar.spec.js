import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CriteriaWorkspaceBar from '@/components/criteria/workspace/CriteriaWorkspaceBar.vue'
import { createTestGlobals } from '@/test-utils'

const published = { id: 1, versionNumber: 1, status: 'published', label: null }
const draft = { id: 4, versionNumber: 2, status: 'draft', label: { ar: 'مراجعة', en: 'Review' } }
const archived = { id: 0, versionNumber: 0, status: 'archived', label: null }

function mountBar(props = {}) {
  return mount(CriteriaWorkspaceBar, {
    props: {
      version: published,
      draft: null,
      versions: [published],
      canManage: true,
      canPublish: true,
      ...props,
    },
    global: { ...createTestGlobals({ locale: 'en' }), stubs: { teleport: true } },
  })
}

function buttons(wrapper) {
  return wrapper
    .findAll('.criteria-bar__actions > button, .criteria-bar__actions > a')
    .map((button) => button.text())
}

describe('CriteriaWorkspaceBar', () => {
  it('offers one click to start editing the published criteria', async () => {
    const wrapper = mountBar()
    expect(wrapper.find('.criteria-bar__title').text()).toBe('Published criteria — version 1')
    expect(buttons(wrapper)).toEqual(['Start editing'])
    await wrapper.find('.criteria-bar__actions > button').trigger('click')
    expect(wrapper.emitted('start')).toHaveLength(1)
  })

  it('shows the blocking notes of the draft and its review and discard actions', async () => {
    const wrapper = mountBar({
      version: draft,
      draft,
      versions: [draft, published],
      problemCount: 2,
    })
    expect(wrapper.find('.criteria-bar__title').text()).toBe('Unpublished changes — version 2')
    expect(wrapper.find('.criteria-bar__meta--danger').text()).toBe('2 notes block publishing')
    expect(buttons(wrapper)).toEqual(['Review and publish', 'Discard changes'])
    expect(wrapper.text()).toContain('Review')
  })

  it('reports a ready draft', () => {
    const wrapper = mountBar({ version: draft, draft, versions: [draft, published] })
    expect(wrapper.find('.criteria-bar__meta--success').text()).toContain('ready to review')
  })

  it('sends the viewer of the published version to the open draft instead of a second one', async () => {
    const wrapper = mountBar({ draft, versions: [draft, published] })
    expect(buttons(wrapper)).toEqual(['Continue the unpublished changes'])
    await wrapper.find('.criteria-bar__actions > button').trigger('click')
    expect(wrapper.emitted('show-version')[0]).toEqual([4])
  })

  it('reads only for a role without criteria.manage and returns from an archived version', () => {
    expect(buttons(mountBar({ canManage: false, canPublish: false }))).toEqual([])
    const wrapper = mountBar({ version: archived, versions: [published, archived] })
    expect(buttons(wrapper)).toContain('Back to the current version')
  })
})
