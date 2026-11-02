import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useRegions } from '@/composables/useRegions'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { useAuthFlowStore } from '@/stores/authFlow'
import { termsService } from '@/services/termsService'
import { registrationSchema } from '@/utils/entitySchemas'
import { emptyProfileValues, registrationPayload } from '@/utils/entityPayload'
import { AUTH_FLOW_INTENTS } from '@/constants/loginSteps'
import { ROUTE_NAMES } from '@/constants/routeNames'

const REGISTRATION_DEFAULTS = {
  officialEmail: '',
  password: '',
  passwordConfirmation: '',
  termsAccepted: false,
  marketingConsent: false,
}

export function useEntityRegistration() {
  const { t } = useI18n()
  const router = useRouter()
  const toast = useToast()
  const auth = useAuthStore()
  const authFlow = useAuthFlowStore()
  const regions = useRegions()
  const terms = ref(null)
  const termsStatus = ref('loading')

  async function loadTerms() {
    termsStatus.value = 'loading'
    try {
      const { data } = await termsService.current()
      terms.value = data
      termsStatus.value = data?.terms ? 'success' : 'unavailable'
    } catch {
      termsStatus.value = 'error'
    }
  }

  function load() {
    return Promise.all([loadTerms(), regions.load()])
  }

  const status = computed(() => {
    if (termsStatus.value === 'loading' || regions.status.value === 'loading') return 'loading'
    if (termsStatus.value === 'error' || regions.status.value === 'error') return 'error'
    return termsStatus.value
  })

  const form = useValidatedForm({
    schema: computed(() => registrationSchema(t)),
    initialValues: { ...emptyProfileValues(), ...REGISTRATION_DEFAULTS },
    action: 'entities.register.submit',
    submit: async (values) => {
      const outcome = await auth.register(registrationPayload(values, terms.value))
      authFlow.setChallenge({
        challenge: outcome.otp,
        officialEmail: values.officialEmail.trim(),
        intent: AUTH_FLOW_INTENTS.REGISTER,
      })
      if (outcome.message) toast.info(outcome.message)
      await router.push({ name: ROUTE_NAMES.AUTH_OTP })
    },
  })

  return { form, terms, status, load, regions }
}
