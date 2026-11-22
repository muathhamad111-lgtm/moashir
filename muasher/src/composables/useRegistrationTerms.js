import { ref } from 'vue'
import { takeRegistrationRead } from '@/composables/registration/registrationReads'

export function useRegistrationTerms() {
  const terms = ref(null)
  const termsStatus = ref('loading')

  async function loadTerms() {
    termsStatus.value = 'loading'
    try {
      const { data } = await takeRegistrationRead('terms')
      terms.value = data
      termsStatus.value = data?.terms ? 'success' : 'unavailable'
    } catch {
      termsStatus.value = 'error'
    }
  }

  return { terms, termsStatus, loadTerms }
}
