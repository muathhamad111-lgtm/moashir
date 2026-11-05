import { describe, expect, it } from 'vitest'
import { defineComponent, h, nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import { useForm } from 'vee-validate'
import { string as yupString, object as yupObject } from 'yup'
import FieldIconSelect from '@/components/form/FieldIconSelect.vue'
import { createTestGlobals } from '@/test-utils'

function factory({ icon = 'award', optional = false, schema } = {}) {
  let form
  const Host = defineComponent({
    setup() {
      form = useForm({ initialValues: { icon }, validationSchema: schema })
      return () =>
        h(FieldIconSelect, {
          name: 'icon',
          label: 'الأيقونة',
          hint: 'اختر أيقونة من مجموعة أيقونات المنصة.',
          options: ['award', 'search', 'chart-line'],
          optional,
        })
    },
  })
  const wrapper = mount(Host, { global: createTestGlobals() })
  return { wrapper, form: () => form }
}

describe('FieldIconSelect', () => {
  it('previews the chosen icon and lists every option with its glyph', async () => {
    const { wrapper } = factory()
    expect(wrapper.find('.icon-field__preview svg').attributes('data-icon')).toBe('award')
    await wrapper.find('.select2-search__input').trigger('focus')
    const options = wrapper.findAll('.select2-search__option')
    expect(options.map((option) => option.text())).toEqual(['award', 'search', 'chart-line'])
    expect(options.map((option) => option.find('svg').attributes('data-icon'))).toEqual([
      'award',
      'search',
      'chart-line',
    ])
    expect(wrapper.find('.icon-field__hint').text()).toBe('اختر أيقونة من مجموعة أيقونات المنصة.')
  })

  it('offers a "no icon" choice for optional fields and clears the value with it', async () => {
    const { wrapper, form } = factory({ optional: true })
    await wrapper.find('.select2-search__input').trigger('focus')
    const none = wrapper.findAll('.select2-search__option')[0]
    expect(none.text()).toBe('بدون أيقونة')
    expect(none.find('svg').exists()).toBe(false)
    await none.trigger('click')
    expect(form().values.icon).toBe('')
    expect(wrapper.find('.icon-field__preview svg').exists()).toBe(false)
  })

  it('shows the validation message instead of the hint', async () => {
    const schema = yupObject({ icon: yupString().oneOf(['search'], 'اختر أيقونة من القائمة.') })
    const { wrapper, form } = factory({ schema })
    await form().validate()
    await nextTick()
    expect(wrapper.find('.icon-field__error').text()).toBe('اختر أيقونة من القائمة.')
    expect(wrapper.find('.icon-field__hint').exists()).toBe(false)
  })
})
