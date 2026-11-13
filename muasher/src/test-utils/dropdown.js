import { flushPromises } from '@vue/test-utils'
import AppSelect from '@/components/form/AppSelect.vue'
import { sameValue } from '@/utils/dropdown/optionValue'

export function findSelect(wrapper, { name, label }) {
  return wrapper
    .findAllComponents(AppSelect)
    .find((select) => (name ? select.props('name') === name : select.props('label') === label))
}

export function optionValuesOf(select) {
  return select.props('options').map((option) => option.value)
}

export function optionLabelsOf(select) {
  return select.props('options').map((option) => option.label)
}

export async function chooseOption(select, value) {
  await select.find('button[role="combobox"]').trigger('click')
  const index = select.props('options').findIndex((option) => sameValue(option.value, value))
  await select.findAll('[role="option"]')[index].trigger('click')
  await flushPromises()
}

export async function chooseNamedOption(wrapper, name, value) {
  await chooseOption(findSelect(wrapper, { name }), value)
}

export async function chooseLabelledOption(wrapper, label, value) {
  await chooseOption(findSelect(wrapper, { label }), value)
}
