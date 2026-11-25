import { afterEach, describe, expect, it, vi } from 'vitest'
import { computed, ref } from 'vue'
import { mount } from '@vue/test-utils'
import ColumnManager from '@/components/data-list/ColumnManager.vue'
import { createTestGlobals } from '@/test-utils'
import { toast } from '@/composables/useToast'

const LABELS = {
  level: 'Level',
  status: 'Status',
  assessor: 'Assessor',
  dueAt: 'Due',
  path: 'Path',
}
const column = (key) => ({ key, label: LABELS[key] })

// A stand-in for useTableColumnChoice: four optional columns, the request before them, actions after.
function choiceOf({ visible = ['level', 'status', 'assessor'] } = {}) {
  const shown = ref(visible)
  const keys = Object.keys(LABELS)
  return {
    ordered: computed(() => [
      ...shown.value.map(column),
      ...keys.filter((key) => !shown.value.includes(key)).map(column),
    ]),
    leading: computed(() => [{ key: 'number', label: 'Request' }]),
    trailing: computed(() => [{ key: 'actions', label: 'Actions' }]),
    visible: shown,
    defaults: computed(() => ['level', 'status', 'assessor', 'dueAt']),
    lockedDataCount: computed(() => 1),
    differsFromSaved: ref(false),
    applyToView: vi.fn(),
    saveAsDefault: vi.fn(),
  }
}

let wrapper

function open(choice = choiceOf()) {
  wrapper = mount(ColumnManager, {
    props: { choice },
    attachTo: document.body,
    global: { ...createTestGlobals({ locale: 'en' }), stubs: { teleport: true } },
  })
  return wrapper.find('.column-manager__trigger').trigger('click')
}

const rows = () => wrapper.findAll('.sortable-list__row').map((row) => row.text())
const checkbox = (name) => wrapper.find(`input[name="column-${name}"]`)
const button = (text) => wrapper.findAll('button').find((node) => node.text() === text)

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
})

describe('ColumnManager', () => {
  it('lists the columns in the viewer order with the locked ones marked always shown', async () => {
    await open()
    expect(rows()).toEqual(['Level', 'Status', 'Assessor', 'Due', 'Path'])
    expect(wrapper.findAll('.column-manager__locked-row').map((row) => row.text())).toEqual([
      'RequestAlways shown',
      'ActionsAlways shown',
    ])
    expect(wrapper.find('.column-manager__all').text()).toContain('Select (4)')
  })

  it('applies the shown columns in their new order to this view only', async () => {
    const choice = choiceOf()
    await open(choice)
    await wrapper
      .findAll('.sortable-list__row')[2]
      .find('.sortable-list__moves button')
      .trigger('click')
    await checkbox('path').setValue(true)
    await button('Apply').trigger('click')
    expect(choice.applyToView).toHaveBeenCalledWith(['level', 'assessor', 'status', 'path'])
    expect(choice.saveAsDefault).not.toHaveBeenCalled()
  })

  it('saves the choice as the default and confirms it', async () => {
    const success = vi.spyOn(toast, 'success')
    const choice = choiceOf()
    await open(choice)
    await checkbox('assessor').setValue(false)
    await button('Save as default').trigger('click')
    expect(choice.saveAsDefault).toHaveBeenCalledWith(['level', 'status'])
    expect(success).toHaveBeenCalledWith('Columns saved as your default for this table.')
  })

  it('keeps three columns visible, the identifier included', async () => {
    await open()
    await checkbox('assessor').setValue(false)
    expect(checkbox('level').element.disabled).toBe(true)
    expect(checkbox('status').element.disabled).toBe(true)
    expect(wrapper.find('.column-manager__minimum').exists()).toBe(true)
    await wrapper.find('input[name="columns-all"]').setValue(true)
    await wrapper.find('input[name="columns-all"]').setValue(false)
    expect(wrapper.find('.column-manager__all').text()).toContain('Select (3)')
  })

  it('filters by name and pauses reordering while searching', async () => {
    await open()
    await wrapper.find('input[name="columnSearch"]').setValue('stat')
    expect(rows()).toEqual(['Status'])
    expect(wrapper.find('.sortable-list__moves button').element.disabled).toBe(true)
    await wrapper.find('input[name="columnSearch"]').setValue('zzz')
    expect(wrapper.find('.column-manager__none').exists()).toBe(true)
  })

  it('resets the dialog to the table defaults and drops changes on close', async () => {
    const choice = choiceOf({ visible: ['path'] })
    await open(choice)
    await button('Reset').trigger('click')
    expect(rows()).toEqual(['Level', 'Status', 'Assessor', 'Due', 'Path'])
    expect(checkbox('dueAt').element.checked).toBe(true)
    await button('Close').trigger('click')
    expect(choice.applyToView).not.toHaveBeenCalled()
    expect(choice.saveAsDefault).not.toHaveBeenCalled()
  })

  it('marks a view that differs from the saved default', async () => {
    const choice = choiceOf()
    choice.differsFromSaved.value = true
    await open(choice)
    const trigger = wrapper.find('.column-manager__trigger')
    expect(trigger.find('.column-manager__dot').exists()).toBe(true)
    expect(trigger.attributes('aria-label')).toBe('Columns (Unsaved changes)')
  })
})
