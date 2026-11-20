import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import CriteriaNodeDetails from '@/components/criteria/workspace/CriteriaNodeDetails.vue'
import { indexTree, ancestorsOf } from '@/utils/criteriaTree'
import { createTestGlobals } from '@/test-utils'
import { workspaceTree } from './criteriaTreeFixture'

vi.mock('@/services/criteriaService', () => ({ criteriaService: {} }))

const index = indexTree(workspaceTree())

function mountDetails(key, props = {}) {
  return mount(CriteriaNodeDetails, {
    props: {
      item: index.get(key),
      index,
      path: ancestorsOf(index, key),
      canEdit: true,
      saveNode: vi.fn(),
      reload: vi.fn(),
      ...props,
    },
    global: { ...createTestGlobals({ locale: 'en' }), stubs: { teleport: true } },
  })
}

describe('CriteriaNodeDetails', () => {
  it('shows where the node sits and lets the editor jump up the path', async () => {
    const wrapper = mountDetails('point-31')
    expect(wrapper.find('.criteria-details__title').text()).toBe('Approved plan')
    expect(wrapper.find('.criteria-details__path').text()).toContain('Leadership')
    expect(wrapper.text()).toContain('Indicator · M1.1.1')
    expect(
      wrapper.find('input[name="pointScoringType"][value="achievement"]').element.checked,
    ).toBe(true)
    await wrapper.findAll('.criteria-details__path-link')[1].trigger('click')
    expect(wrapper.emitted('select')[0]).toEqual(['main-11'])
  })

  it('edits a sub-criterion inline with its evaluation method and indicator weights', () => {
    const wrapper = mountDetails('sub-21')
    expect(wrapper.find('.form-panel__title').text()).toBe('Details')
    expect(wrapper.find('input[name="subNameEn"]').element.value).toBe('Planning')
    expect(wrapper.text()).toContain('Evaluation method')
    expect(wrapper.text()).toContain('Weight split over the indicators')
    expect(wrapper.text()).toContain('Indicator scoring type')
  })

  it('opens the inline form with the attachments the criterion already has, so saving keeps them', () => {
    const wrapper = mountDetails('sub-21')
    const names = wrapper
      .findAll('.attachments-editor input[dir="ltr"]')
      .map((input) => input.element.value)
    expect(names).toContain('Plan')
  })

  it('offers to split the weight equally when a full main criterion gets a new sub-criterion', () => {
    const wrapper = mountDetails('main-11')
    expect(wrapper.text()).toContain('Split the weight equally and add a sub-criterion')
  })

  it('emits the node actions', async () => {
    const wrapper = mountDetails('sub-22')
    const byText = (label) => wrapper.findAll('button').find((button) => button.text() === label)
    await byText('Deactivate').trigger('click')
    await byText('Delete').trigger('click')
    expect(wrapper.emitted('toggle')).toHaveLength(1)
    expect(wrapper.emitted('remove')).toHaveLength(1)
  })

  it('reads only without criteria.manage', () => {
    const wrapper = mountDetails('main-11', { canEdit: false })
    expect(wrapper.find('.form-panel__footer').exists()).toBe(false)
    expect(wrapper.find('.form-panel__fields').attributes('disabled')).toBeDefined()
    expect(wrapper.text()).not.toContain('Delete')
  })

  it('shows an attachment error from the server at the attachment field it belongs to', async () => {
    const rejection = Object.assign(new Error('invalid'), {
      normalized: {
        message: 'Invalid input',
        fieldErrors: { 'attachments.0.guidance.ar': ['Enter the Arabic guidance.'] },
      },
    })
    const saveNode = vi.fn().mockRejectedValue(rejection)
    const wrapper = mountDetails('sub-21', { saveNode })
    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() => expect(saveNode).toHaveBeenCalled())
    await flushPromises()
    const guidance = wrapper.find('textarea[name="attachmentGuidanceAr0"]')
    expect(guidance.attributes('aria-invalid')).toBe('true')
    expect(wrapper.find('.attachments-editor details').attributes('open')).toBeDefined()
  })
})
