import { ref } from 'vue'
import { takeRegistrationRead } from '@/composables/registration/registrationReads'

export function useRegistrationOptions() {
  const nationalIdRequired = ref(false)

  async function loadOptions() {
    try {
      const { data } = await takeRegistrationRead('options')
      nationalIdRequired.value = data?.nationalIdRequired === true
    } catch {
      nationalIdRequired.value = false
    }
  }

  return { nationalIdRequired, loadOptions }
}
