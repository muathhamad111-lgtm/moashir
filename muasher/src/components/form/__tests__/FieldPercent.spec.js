import { describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { useForm } from 'vee-validate'
import FieldPercent from '@/components/form/FieldPercent.vue'

function mountField() {
  let form
  const Host = defineComponent({
    setup() {
      form = useForm({ initialValues: { grade: '' } })
      return () =>
        h(FieldPercent, { name: 'grade', label: 'Governance', integerDigits: 3, decimals: 2 })
    },
  })
  return { wrapper: mount(Host), values: () => form.values }
}

describe('FieldPercent', () => {
  it('shows a percent sign and keeps the input left-to-right', () => {
    const { wrapper } = mountField()
    expect(wrapper.find('.field__suffix').text()).toBe('%')
    expect(wrapper.find('.field__suffix').attributes('aria-hidden')).toBe('true')
    expect(wrapper.find('.field__affixed').attributes('dir')).toBe('ltr')
    expect(wrapper.find('input').attributes('inputmode')).toBe('decimal')
  })

  it('limits typing to three whole digits and the allowed decimals', async () => {
    const { wrapper, values } = mountField()
    const input = wrapper.find('input')
    await input.setValue('21343.5678')
    expect(input.element.value).toBe('213.56')
    expect(values().grade).toBe('213.56')
    await input.setValue('٨٧٫٤٥٩%')
    expect(input.element.value).toBe('87.45')
  })
})
