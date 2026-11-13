import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PathPrimaryAction from '@/components/paths/PathPrimaryAction.vue'
import { createTestGlobals } from '@/test-utils'

function factory(props) {
  return mount(PathPrimaryAction, { props, global: createTestGlobals({ locale: 'ar' }) })
}

describe('PathPrimaryAction', () => {
  it('runs a command in place for a free start and stays a link otherwise', async () => {
    const run = factory({
      action: { labelKey: 'paths.actions.startAccredited', run: 'startAccredited', icon: 'x' },
    })
    expect(run.element.tagName).toBe('BUTTON')
    await run.trigger('click')
    expect(run.emitted('run')).toEqual([['startAccredited']])
    const busy = factory({
      action: { labelKey: 'paths.actions.startAccredited', run: 'startAccredited', icon: 'x' },
      busy: true,
    })
    expect(busy.attributes('disabled')).toBeDefined()
    const link = factory({
      action: {
        labelKey: 'paths.actions.payAndStart',
        to: { name: 'assessment.self.pay' },
        icon: 'x',
      },
    })
    expect(link.attributes('href')).toBe('/assessment/self/pay')
    expect(link.text()).toBe('ادفع وابدأ')
  })
})
