import { ref } from 'vue'
import { identityService } from '@/services/identityService'

export function useRegistrationOptions() {
  const nationalIdRequired = ref(false)

  async function loadOptions() {
    try {
      const { data } = await identityService.registrationOptions()
      nationalIdRequired.value = data?.nationalIdRequired === true
    } catch {
      nationalIdRequired.value = false
    }
  }

  return { nationalIdRequired, loadOptions }
}
