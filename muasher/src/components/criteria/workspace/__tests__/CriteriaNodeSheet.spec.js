import { afterEach, describe, expect, it, vi } from 'vitest'
import { DOMWrapper, mount } from '@vue/test-utils'
import CriteriaNodeSheet from '@/components/criteria/workspace/CriteriaNodeSheet.vue'
import AppMenu from '@/components/ui/AppMenu.vue'
import { useConfirm } from '@/composables/useConfirm'
import { indexTree, ancestorsOf } from '@/utils/criteriaTree'
import { createTestGlobals } from '@/test-utils'
import { workspaceTree } from './criteriaTreeFixture'

vi.mock('@/services/criteriaService', () => ({ criteriaService: {} }))

const index = indexTree(workspaceTree())

let mounted = null

afterEach(() => {
  mounted?.unmount()
  mounted = null
})

// The sheet is a teleported drawer: mount it for real (a teleport stub re-creates its content on
// every update).
function mountSheet(key, props = {}) {
  mounted?.unmount()
  mounted = mount(CriteriaNodeSheet, {
    attachTo: document.body,
    props: {
      item: index.get(key),
      index,
      path: ancestorsOf(index, key),
      modelWeight: '1000.00',
      canEdit: true,
      saveNode: vi.fn(),
      reload: vi.fn(),
      ...props,
    },
    global: createTestGlobals({ locale: 'en' }),
  })
  return mounted
}

// The drawer is teleported to the body, so its content is read from the document.
const sheet = () => new DOMWrapper(document.querySelector('.modal'))

const tabLabels = () =>
  sheet()
    .findAll('[role="tab"]')
    .map((tab) => tab.text())

function tabsOf(key) {
  mountSheet(key)
  return tabLabels()
}

async function openTab(label) {
  await sheet()
    .findAll('[role="tab"]')
    .find((tab) => tab.text().startsWith(label))
    .trigger('click')
}

describe('CriteriaNodeSheet', () => {
  it('shows the tabs that fit each kind of criterion', () => {
    expect(tabsOf('main-11')).toEqual([
      'Basics',
      'Weight',
      'Sub-criteria',
      'Evaluation',
      'Attachments',
    ])
    expect(tabsOf('sub-21')).toEqual([
      'Basics',
      'Weight',
      'Evaluation',
      'Attachments',
      'Indicators',
      'Advanced',
    ])
    expect(tabsOf('sub-22')).toEqual([
      'Basics',
      'Weight',
      'Evaluation',
      'Attachments',
      'Indicators',
      'Advanced',
    ])
    expect(tabsOf('point-31')).toEqual(['Basics', 'Weight', 'Evaluation', 'Attachments'])
    // An indicator of a directly evaluated sub-criterion has no weight and no scoring type.
    expect(tabsOf('point-32')).toEqual(['Basics', 'Attachments'])
  })

  it('shows where the node sits and lets the editor jump up the path', async () => {
    const wrapper = mountSheet('point-31')
    expect(sheet().find('.modal__title').text()).toBe('Approved plan')
    expect(sheet().text()).toContain('Indicator · M1.1.1')
    await sheet().findAll('.criteria-details__path-link')[1].trigger('click')
    await vi.waitFor(() => expect(wrapper.emitted('open')?.[0]).toEqual(['sub-21', undefined]))
  })

  it('keeps one form across its tabs, so the attachments the criterion has are saved with its name', async () => {
    const saveNode = vi.fn().mockResolvedValue({})
    mountSheet('sub-21', { saveNode })
    expect(sheet().find('input[name="subNameEn"]').element.value).toBe('Planning')
    await openTab('Attachments')
    const names = sheet()
      .findAll('.attachments-editor input[dir="ltr"]')
      .map((input) => input.element.value)
    expect(names).toContain('Plan')
    await sheet().find('form').trigger('submit')
    await vi.waitFor(() => expect(saveNode).toHaveBeenCalled())
    const { payload } = saveNode.mock.calls[0][0]
    expect(payload.attachments.map((attachment) => attachment.name.en)).toEqual(['Plan'])
    expect(payload).not.toHaveProperty('weight')
  })

  it('edits the split over the children in the weight tab', async () => {
    const wrapper = mountSheet('sub-21')
    await openTab('Weight')
    expect(sheet().text()).toContain('Weight split over the indicators')
    expect(sheet().text()).toContain("This item's weight is edited in its parent's weight split.")
    await sheet().find('.criteria-sheet__note button').trigger('click')
    await vi.waitFor(() => expect(wrapper.emitted('open')?.[0]).toEqual(['main-11', 'weight']))
  })

  it('shows the evaluation method and the scoring type of a sub-criterion', async () => {
    mountSheet('sub-21')
    await openTab('Evaluation')
    expect(sheet().text()).toContain('Evaluation method')
    expect(sheet().text()).toContain('Indicator scoring type')
  })

  it('emits the node actions from its menu', () => {
    const wrapper = mountSheet('sub-22')
    const menu = wrapper.findComponent(AppMenu)
    menu.vm.$emit('select', 'toggle')
    menu.vm.$emit('select', 'remove')
    expect(wrapper.emitted('toggle')).toHaveLength(1)
    expect(wrapper.emitted('remove')).toHaveLength(1)
  })

  it('shows values as text, not disabled fields, without criteria.manage', () => {
    const wrapper = mountSheet('main-11', { canEdit: false })
    expect(sheet().find('form').exists()).toBe(false)
    expect(sheet().find('.criteria-summary').text()).toContain('Leadership')
    expect(wrapper.findComponent(AppMenu).exists()).toBe(false)
  })

  it('marks the tab that holds a server error and shows it at its field', async () => {
    const rejection = Object.assign(new Error('invalid'), {
      normalized: {
        message: 'Invalid input',
        fieldErrors: { 'attachments.0.guidance.ar': ['Enter the Arabic guidance.'] },
      },
    })
    const saveNode = vi.fn().mockRejectedValue(rejection)
    mountSheet('sub-21', { saveNode })
    await sheet().find('form').trigger('submit')
    const attachmentsTab = () =>
      sheet()
        .findAll('[role="tab"]')
        .find((tab) => tab.text().startsWith('Attachments'))
    await vi.waitFor(() => expect(attachmentsTab().find('.tabs__badge').exists()).toBe(true))
    await attachmentsTab().trigger('click')
    const guidance = sheet().find('textarea[name="attachmentGuidanceAr0"]')
    expect(guidance.attributes('aria-invalid')).toBe('true')
  })

  describe('unsaved edits', () => {
    const { confirmState, accept, dismiss } = useConfirm()

    async function editName(value) {
      await sheet().find('input[name="subNameEn"]').setValue(value)
    }

    it('closes at once when nothing was edited', async () => {
      const wrapper = mountSheet('sub-21')
      await sheet().find('.modal__close').trigger('click')
      await vi.waitFor(() => expect(wrapper.emitted('close')).toHaveLength(1))
      expect(confirmState.isOpen).toBe(false)
    })

    it('asks before closing with unsaved edits and keeps them when the editor stays', async () => {
      const wrapper = mountSheet('sub-21')
      await editName('Planning and goals')
      await sheet().find('.modal__close').trigger('click')
      await vi.waitFor(() => expect(confirmState.isOpen).toBe(true))
      expect(confirmState.title).toBe('Discard the changes?')
      dismiss()
      await vi.waitFor(() => expect(confirmState.isOpen).toBe(false))
      expect(wrapper.emitted('close')).toBeUndefined()
      expect(sheet().find('input[name="subNameEn"]').element.value).toBe('Planning and goals')

      await sheet().find('.modal__close').trigger('click')
      await vi.waitFor(() => expect(confirmState.isOpen).toBe(true))
      accept()
      await vi.waitFor(() => expect(wrapper.emitted('close')).toHaveLength(1))
    })

    it('closes without asking once the save went through and the criterion reloaded', async () => {
      const saveNode = vi.fn().mockResolvedValue({})
      const wrapper = mountSheet('sub-21', { saveNode })
      await editName('Planning and goals')
      await sheet().find('form').trigger('submit')
      await vi.waitFor(() => expect(saveNode).toHaveBeenCalled())
      const item = index.get('sub-21')
      await wrapper.setProps({
        item: {
          ...item,
          node: { ...item.node, name: { ...item.node.name, en: 'Planning and goals' } },
        },
      })
      await sheet().find('.modal__close').trigger('click')
      await vi.waitFor(() => expect(wrapper.emitted('close')).toHaveLength(1))
      expect(confirmState.isOpen).toBe(false)
    })

    it('asks before opening another node with unsaved attachment edits', async () => {
      const wrapper = mountSheet('sub-21')
      await openTab('Attachments')
      await sheet().find('.attachments-editor input[dir="ltr"]').setValue('Signed plan')
      await sheet().find('.criteria-details__path-link').trigger('click')
      await vi.waitFor(() => expect(confirmState.isOpen).toBe(true))
      dismiss()
      await vi.waitFor(() => expect(confirmState.isOpen).toBe(false))
      expect(wrapper.emitted('open')).toBeUndefined()
    })
  })
})
