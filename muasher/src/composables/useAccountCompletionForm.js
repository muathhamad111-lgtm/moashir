import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { object } from 'yup'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { useAccountCompletionStore } from '@/stores/accountCompletion'
import { identityService } from '@/services/identityService'
import { NATIONAL_ID_STATES } from '@/constants/identityChanges'
import { normalizeNationalId } from '@/utils/identity/nationalId'
import { nationalIdRule } from '@/utils/identity/nationalIdSchema'
import { genderRule } from '@/utils/identity/genderSchema'

const EMPTY = Object.freeze({ nationalId: '', gender: '' })

export function useAccountCompletionForm() {
  const { t } = useI18n()
  const toast = useToast()
  const auth = useAuthStore()
  const completion = useAccountCompletionStore()
  const asksNationalId = computed(() => completion.asksNationalId)
  const asksGender = computed(() => completion.needsGender)

  async function saveNationalId(value) {
    const { data } = await identityService.provideNationalId(normalizeNationalId(value))
    auth.applyNationalIdStatus(data)
    return data.nationalIdStatus === NATIONAL_ID_STATES.PENDING
  }

  const form = useValidatedForm({
    schema: computed(() =>
      object({
        ...(asksNationalId.value ? { nationalId: nationalIdRule(t) } : {}),
        ...(asksGender.value ? { gender: genderRule(t) } : {}),
      }),
    ),
    initialValues: { ...EMPTY },
    action: 'nationalIdentity.complete.submit',
    submit: async (values) => {
      if (asksGender.value) {
        await identityService.recordGender(values.gender)
        auth.completeGender()
      }
      const pending = asksNationalId.value && (await saveNationalId(values.nationalId))
      toast.success(
        t(
          pending ? 'nationalIdentity.complete.sentForApproval' : 'nationalIdentity.complete.saved',
        ),
      )
      completion.close()
    },
  })

  watch(
    () => completion.dialogOpen,
    (open) => {
      if (open) form.resetForm({ values: { ...EMPTY } })
    },
  )

  return { form, asksNationalId, asksGender }
}
