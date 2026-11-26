import { afterEach, describe, expect, it, vi } from 'vitest'
import { h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import PageActions from '@/components/ui/PageActions.vue'
import { createTestGlobals } from '@/test-utils'
import { stubMatchMedia } from '@/test-utils/matchMedia'

afterEach(() => {
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
})

const PHONE = 390
const DESKTOP = 1280

function factory({ width, buttons = ['Edit', 'Delete'], onDelete = () => {} }) {
  stubMatchMedia({ width })
  return mount(PageActions, {
    attachTo: document.body,
    props: { label: 'Entity actions' },
    attrs: { class: 'entity-record__actions' },
    global: createTestGlobals({ locale: 'en' }),
    slots: {
      default: () =>
        buttons.map((label) =>
          h(
            'button',
            {
              type: 'button',
              class: label === 'Delete' ? 'btn btn--danger' : 'btn',
              onClick: label === 'Delete' ? onDelete : undefined,
            },
            label,
          ),
        ),
    },
  })
}

describe('PageActions', () => {
  it('keeps the actions in a row from md up', () => {
    const wrapper = factory({ width: DESKTOP })
    expect(wrapper.findAll('button').map((b) => b.text())).toEqual(['Edit', 'Delete'])
    expect(wrapper.find('[data-testid="page-actions-toggle"]').exists()).toBe(false)
    expect(wrapper.classes()).toContain('entity-record__actions')
  })

  it('keeps a single action as its own button on a phone', () => {
    const wrapper = factory({ width: PHONE, buttons: ['Assign'] })
    expect(wrapper.find('[data-testid="page-actions-toggle"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Assign')
  })

  it('groups two or more actions behind one button that opens a bottom sheet on a phone', async () => {
    const wrapper = factory({ width: PHONE })
    const toggle = wrapper.find('[data-testid="page-actions-toggle"]')
    expect(toggle.attributes('aria-haspopup')).toBe('dialog')
    expect(wrapper.text()).not.toContain('Delete')

    await toggle.trigger('click')
    const sheet = document.querySelector('.modal--sheet [data-testid="page-actions-sheet"]')
    expect(sheet).not.toBeNull()
    expect(document.querySelector('.modal--sheet .modal__title').textContent).toBe('Entity actions')
    expect(sheet.querySelector('.btn--danger').textContent).toBe('Delete')
  })

  it('runs the chosen action and closes the sheet', async () => {
    const onDelete = vi.fn()
    const wrapper = factory({ width: PHONE, onDelete })
    await wrapper.find('[data-testid="page-actions-toggle"]').trigger('click')
    document.querySelector('.modal--sheet .btn--danger').click()
    await flushPromises()
    expect(onDelete).toHaveBeenCalledOnce()
    expect(document.querySelector('.modal--sheet')).toBeNull()
  })
})
