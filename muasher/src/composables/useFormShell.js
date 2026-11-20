import { computed, watch } from 'vue'
import FormModal from '@/components/modal/FormModal.vue'
import FormPanel from '@/components/form/FormPanel.vue'
import FormSteps from '@/components/modal/FormSteps.vue'

// `wizard` (optional): the create form opens as steps in a drawer (FormSteps) instead of one dialog.
export function useFormShell({ inline, open, record, reset, wizard = () => false }, emit) {
  const shell = computed(() => {
    if (inline()) return FormPanel
    return wizard() ? FormSteps : FormModal
  })

  watch(open, (isOpen) => {
    if (isOpen && !inline()) reset()
  })

  watch(
    record,
    () => {
      if (inline()) reset()
    },
    { immediate: true },
  )

  function finish() {
    if (!inline()) emit('update:modelValue', false)
  }

  function toggle(isOpen) {
    if (inline()) {
      if (!isOpen) reset()
      return
    }
    emit('update:modelValue', isOpen)
  }

  return { shell, finish, toggle }
}
