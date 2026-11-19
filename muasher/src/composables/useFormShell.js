import { computed, watch } from 'vue'
import FormModal from '@/components/modal/FormModal.vue'
import FormPanel from '@/components/form/FormPanel.vue'

export function useFormShell({ inline, open, record, reset }, emit) {
  const shell = computed(() => (inline() ? FormPanel : FormModal))

  watch(open, (isOpen) => {
    if (isOpen && !inline()) reset()
  })

  watch(record, () => {
    if (inline()) reset()
  })

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
