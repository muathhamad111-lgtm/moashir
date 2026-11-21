import { flushPromises } from '@vue/test-utils'

export async function openListSearch(wrapper) {
  const toggle = wrapper.find('.expandable-search__toggle')
  if (toggle.exists()) await toggle.trigger('click')
  return wrapper.find('.expandable-search__input')
}

export async function openFilterDialog(wrapper) {
  await wrapper.find('.filter-dialog__toggle').trigger('click')
  await flushPromises()
}

const APPLY_LABELS = ['Apply', 'تطبيق']

export async function applyFilterDialog() {
  const apply = [...document.querySelectorAll('[role="dialog"] button')].find((button) =>
    APPLY_LABELS.includes(button.textContent.trim()),
  )
  apply.click()
  await flushPromises()
}
