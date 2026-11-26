import { describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobals } from '@/test-utils'
import Form from '../Form.vue'

function workspaceStub(assessment) {
  return defineComponent({
    name: 'AccreditedFormWorkspace',
    setup(_, { slots }) {
      return () => h('div', slots['extra-actions']?.({ assessment }))
    },
  })
}

async function mountWith(assessment) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  await globals.router.push('/assessment/advanced/form/9301')
  const wrapper = mount(Form, {
    global: {
      plugins: globals.plugins,
      stubs: { teleport: true, AccreditedFormWorkspace: workspaceStub(assessment) },
    },
  })
  await flushPromises()
  return wrapper
}

const deleteButton = (wrapper) =>
  wrapper.findAll('button').find((b) => b.text() === 'Delete the draft')

describe('advanced request form', () => {
  it('offers to delete an unpaid award draft', async () => {
    const wrapper = await mountWith({ id: 9301, number: 'ADV-1', status: 'draft', isPaid: false })
    expect(deleteButton(wrapper)).toBeDefined()
  })

  it('hides the delete action on a paid award draft, whose fee only a refund returns', async () => {
    const wrapper = await mountWith({ id: 9301, number: 'ADV-1', status: 'draft', isPaid: true })
    expect(deleteButton(wrapper)).toBeUndefined()
  })
})
