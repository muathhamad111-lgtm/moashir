import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useRegions } from '@/composables/useRegions'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { useAuthFlowStore } from '@/stores/authFlow'
import { useRegistrationTerms } from '@/composables/useRegistrationTerms'
import { useRegistrationOptions } from '@/composables/useRegistrationOptions'
import { takeRegistrationRead } from '@/composables/registration/registrationReads'
import { nationalIdRule } from '@/utils/identity/nationalIdSchema'
import { normalizeNationalId } from '@/utils/identity/nationalId'
import { registrationSchema } from '@/utils/entitySchemas'
import { emptyProfileValues, registrationPayload } from '@/utils/entityPayload'
import { useLoginOutcome } from '@/composables/useLoginOutcome'
import { AUTH_FLOW_INTENTS, LOGIN_STEPS } from '@/constants/loginSteps'

const REGISTRATION_DEFAULTS = {
  nationalId: '',
  officialEmail: '',
  emailVerificationToken: '',
  password: '',
  passwordConfirmation: '',
  termsAccepted: false,
  hasStrategicPlan: '',
  licenseNumber: '',
  foundedOn: '',
}

function withNationalId(payload, values, required) {
  return required ? { ...payload, nationalId: normalizeNationalId(values.nationalId) } : payload
}

export function useEntityRegistration() {
  const { t } = useI18n()
  const toast = useToast()
  const { continueAfter } = useLoginOutcome()
  const auth = useAuthStore()
  const authFlow = useAuthFlowStore()
  const regions = useRegions()
  const { terms, termsStatus, loadTerms } = useRegistrationTerms()
  const underReview = ref(false)
  const { nationalIdRequired, loadOptions } = useRegistrationOptions()

  function load() {
    return Promise.all([
      loadTerms(),
      regions.load(() => takeRegistrationRead('regions')),
      loadOptions(),
    ])
  }

  const status = computed(() => {
    if (termsStatus.value === 'loading' || regions.status.value === 'loading') return 'loading'
    if (termsStatus.value === 'error' || regions.status.value === 'error') return 'error'
    return termsStatus.value
  })

  const form = useValidatedForm({
    schema: computed(() =>
      nationalIdRequired.value
        ? registrationSchema(t).shape({ nationalId: nationalIdRule(t) })
        : registrationSchema(t),
    ),
    initialValues: { ...emptyProfileValues(), ...REGISTRATION_DEFAULTS },
    action: 'entities.register.submit',
    keepValuesOnUnmount: true,
    submit: async (values) => {
      const outcome = await auth.register(
        withNationalId(registrationPayload(values, terms.value), values, nationalIdRequired.value),
      )
      // The e-mail is already proven: the entity is either waiting for staff review or signed in.
      if (outcome.step === LOGIN_STEPS.PENDING_REVIEW) {
        underReview.value = true
        return
      }
      if (outcome.message) toast.info(outcome.message)
      authFlow.setChallenge({
        challenge: null,
        officialEmail: values.officialEmail.trim(),
        intent: AUTH_FLOW_INTENTS.REGISTER,
      })
      await continueAfter(outcome)
    },
  })

  return { form, status, load, regions, underReview, nationalIdRequired }
}
