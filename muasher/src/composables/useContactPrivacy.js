import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { contactPrivacyService } from '@/services/contactPrivacyService'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { REASON_LENGTH } from '@/constants/entityEnums'
import { downloadJson } from '@/utils/download'

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const EXPORT_FILE = 'contact-messages-export.json'

export function useContactPrivacy() {
  const { t } = useI18n()
  const toast = useToast()
  const { confirm } = useConfirm()
  const form = reactive({ email: '', reason: '' })
  const errors = reactive({ email: '', reason: '' })
  const busy = ref('')
  const exported = ref(null)
  const erased = ref(null)

  function validate() {
    const email = form.email.trim()
    const reason = form.reason.trim()
    errors.email = EMAIL_PATTERN.test(email) ? '' : t('entities.privacy.contact.errors.email')
    errors.reason =
      reason.length >= REASON_LENGTH.MIN && reason.length <= REASON_LENGTH.MAX
        ? ''
        : t('entities.privacy.contact.errors.reason', {
            min: REASON_LENGTH.MIN,
            max: REASON_LENGTH.MAX,
          })
    return !errors.email && !errors.reason ? { email, reason } : null
  }

  function applyServerErrors(error) {
    const fields = error?.normalized?.fieldErrors ?? {}
    errors.email = fields.email?.[0] ?? errors.email
    errors.reason = fields.reason?.[0] ?? errors.reason
    toast.error(error?.normalized?.message || t('errors.unexpected'))
  }

  async function run(kind, execute) {
    const payload = validate()
    if (!payload || busy.value) return
    busy.value = kind
    try {
      await execute(payload)
    } catch (error) {
      applyServerErrors(error)
    } finally {
      busy.value = ''
    }
  }

  function exportMessages() {
    erased.value = null
    return run('export', async (payload) => {
      exported.value = (await contactPrivacyService.exportByEmail(payload)).data
      toast.success(t('entities.privacy.contact.toasts.exported'))
    })
  }

  async function eraseMessages() {
    exported.value = null
    const accepted = await confirm({
      title: t('entities.privacy.contact.eraseTitle'),
      message: t('components.confirm.irreversible'),
      confirmLabel: t('actions.delete'),
      variant: 'danger',
    })
    if (!accepted) return
    await run('erase', async (payload) => {
      erased.value = (await contactPrivacyService.eraseByEmail(payload)).data
      toast.success(t('entities.privacy.contact.toasts.erased'))
    })
  }

  function downloadExport() {
    if (exported.value) downloadJson(exported.value, EXPORT_FILE)
  }

  return { form, errors, busy, exported, erased, exportMessages, eraseMessages, downloadExport }
}
