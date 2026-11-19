import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import NotificationChannelSwitches from '@/components/settings/notifications/NotificationChannelSwitches.vue'
import { createTestGlobals } from '@/test-utils'

function setting(channel, overrides = {}) {
  return { channel, isEnabled: true, isShipped: true, isSwitchable: true, ...overrides }
}

function mountSwitches(props) {
  return mount(NotificationChannelSwitches, {
    props,
    global: createTestGlobals({ locale: 'en' }),
  })
}

describe('NotificationChannelSwitches', () => {
  it('offers in-app and e-mail switches and emits the change', async () => {
    const wrapper = mountSwitches({
      settings: [
        setting('in_app'),
        setting('email', { isEnabled: false, isShipped: false }),
        setting('sms', { isEnabled: false, isShipped: false, isSwitchable: false }),
      ],
    })
    const inputs = wrapper.findAll('input[role="switch"]')
    expect(inputs).toHaveLength(2)
    expect(wrapper.text()).toContain('Added for this notification')
    await inputs[1].setValue(true)
    expect(wrapper.emitted('change')[0][0]).toEqual({ channel: 'email', enabled: true })
  })

  it('locks every switch of a mandatory notification and shows its shipped SMS', () => {
    const wrapper = mountSwitches({
      isMandatory: true,
      settings: [
        setting('in_app', { isEnabled: false, isShipped: false, isSwitchable: false }),
        setting('email', { isSwitchable: false }),
        setting('sms', { isSwitchable: false }),
      ],
    })
    const inputs = wrapper.findAll('input[role="switch"]')
    expect(inputs).toHaveLength(3)
    inputs.forEach((input) => expect(input.attributes('disabled')).toBeDefined())
    expect(wrapper.text()).toContain('Mandatory notification')
  })

  it('offers SMS for a type that can send it, with the provider rule', () => {
    const wrapper = mountSwitches({
      settings: [
        setting('in_app'),
        setting('email'),
        setting('sms', { isEnabled: false, isShipped: false }),
      ],
    })
    expect(wrapper.findAll('input[role="switch"]')).toHaveLength(3)
    expect(wrapper.text()).toContain('Sent only to recipients with a mobile number')
  })
})
