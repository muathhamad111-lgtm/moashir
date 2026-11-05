import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useLoginOutcome } from '@/composables/useLoginOutcome'
import { useAuthStore } from '@/stores/auth'
import { termsService } from '@/services/termsService'
import { AUTH_FLOW_INTENTS } from '@/constants/loginSteps'
import { invitationPayload, invitationSchema } from '@/utils/auth/passwordForms'

const TOKEN_STATES = { 404: 'invalid', 410: 'expired' }

export function useInvitationAcceptance(token) {
  const { t } = useI18n()
  const auth = useAuthStore()
  const { continueAfter } = useLoginOutcome()
  const status = ref('loading')
  const terms = ref(null)
  const termsRequired = computed(() => Boolean(terms.value?.terms))

  async function loadTerms() {
    status.value = 'loading'
    try {
      const { data } = await termsService.current()
      terms.value = data
      status.value = 'ready'
    } catch {
      status.value = 'error'
    }
  }

  const form = useValidatedForm({
    schema: computed(() => invitationSchema(t, { termsRequired: termsRequired.value })),
    initialValues: { password: '', passwordConfirmation: '', termsAccepted: false },
    action: 'auth.invitation.submit',
    submit: async (values) => {
      let outcome
      try {
        outcome = await auth.acceptInvitation(token.value, invitationPayload(values, terms.value))
      } catch (error) {
        const state = TOKEN_STATES[error?.normalized?.status]
        if (!state) throw error
        status.value = state
        return
      }
      await continueAfter(outcome, { intent: AUTH_FLOW_INTENTS.LOGIN })
    },
  })

  onMounted(loadTerms)

  return { form, status, terms, termsRequired, loadTerms }
}
