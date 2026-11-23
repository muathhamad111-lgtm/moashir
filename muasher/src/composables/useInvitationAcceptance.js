import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useLoginOutcome } from '@/composables/useLoginOutcome'
import { useAuthStore } from '@/stores/auth'
import { authService } from '@/services/authService'
import { termsService } from '@/services/termsService'
import { AUTH_FLOW_INTENTS } from '@/constants/loginSteps'
import { invitationPayload, invitationSchema } from '@/utils/auth/passwordForms'
import { toNdaText } from '@/services/mappers/nda'

const TOKEN_STATES = { 404: 'invalid', 410: 'expired' }

function tokenStateOf(error) {
  return TOKEN_STATES[error?.normalized?.status] ?? null
}

// What the invitation page shows before the form: the token state, the terms, the preset national ID and the
// staff agreement, a step between the password and MFA when the invitee's roles are in its scope.
function useInvitationDetails(token) {
  const status = ref('loading')
  const terms = ref(null)
  const presetNationalId = ref(null)
  const nda = ref(null)

  async function load() {
    status.value = 'loading'
    try {
      const [invitation, { data }] = await Promise.all([
        authService.checkInvitation(token.value),
        termsService.current(),
      ])
      presetNationalId.value = invitation?.data?.nationalIdMasked ?? null
      nda.value = toNdaText(invitation?.data?.nda)
      terms.value = data
      status.value = 'ready'
    } catch (error) {
      status.value = tokenStateOf(error) ?? 'error'
    }
  }

  return { status, terms, presetNationalId, nda, load }
}

export function useInvitationAcceptance(token) {
  const { t } = useI18n()
  const auth = useAuthStore()
  const { continueAfter } = useLoginOutcome()
  const details = useInvitationDetails(token)
  const { status, terms, nda } = details
  const onNdaStep = ref(false)
  const termsRequired = computed(() => Boolean(terms.value?.terms))

  async function accept(values) {
    try {
      const payload = invitationPayload(values, terms.value, nda.value)
      return await auth.acceptInvitation(token.value, payload)
    } catch (error) {
      const state = tokenStateOf(error)
      if (!state) throw error
      status.value = state
      return null
    }
  }

  const form = useValidatedForm({
    schema: computed(() => invitationSchema(t, { termsRequired: termsRequired.value })),
    initialValues: { password: '', passwordConfirmation: '', termsAccepted: false, nationalId: '' },
    action: 'auth.invitation.submit',
    // The password fields leave the page during the agreement step; their values must stay for the submit.
    keepValuesOnUnmount: true,
    submit: async (values) => {
      if (nda.value && !onNdaStep.value) {
        onNdaStep.value = true
        return
      }
      const outcome = await accept(values)
      if (outcome) await continueAfter(outcome, { intent: AUTH_FLOW_INTENTS.LOGIN })
    },
  })

  onMounted(details.load)

  return { ...details, form, termsRequired, onNdaStep }
}
