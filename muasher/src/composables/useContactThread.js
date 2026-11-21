import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { contactInboxService } from '@/services/contactInboxService'
import { useSuperAdminReason } from '@/composables/useSuperAdminReason'
import { useToast } from '@/composables/useToast'
import { CONTACT_INBOX_CODES, CONTACT_INBOX_OPEN_STATUSES } from '@/constants/contactInbox'

const NOT_FOUND = 404
const RELOADING_CODES = [CONTACT_INBOX_CODES.CLOSED, CONTACT_INBOX_CODES.TRANSITION_ILLEGAL]

export function useContactThread(messageId) {
  const { t } = useI18n()
  const toast = useToast()
  const { prompt: reasonPrompt, reasonExtra } = useSuperAdminReason()
  const message = ref(null)
  const status = ref('loading')
  const errorMessage = ref('')
  const busy = ref('')
  const fieldErrors = ref({})

  const isOpen = computed(() => CONTACT_INBOX_OPEN_STATUSES.includes(message.value?.status))

  async function load({ silent = false } = {}) {
    if (!silent) status.value = 'loading'
    errorMessage.value = ''
    try {
      message.value = (await contactInboxService.get(messageId.value)).data
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = error?.normalized?.status === NOT_FOUND ? 'missing' : 'error'
    }
  }

  async function rejected(error) {
    const normalized = error?.normalized ?? {}
    fieldErrors.value = normalized.fieldErrors ?? {}
    toast.error(normalized.message || t('errors.unexpected'))
    if (RELOADING_CODES.includes(normalized.code)) await load({ silent: true })
  }

  async function perform(name, run, successKey) {
    if (busy.value) return false
    const extra = await reasonExtra()
    if (!extra) return false
    busy.value = name
    fieldErrors.value = {}
    try {
      message.value = (await run(extra)).data
      toast.success(t(successKey))
      return true
    } catch (error) {
      await rejected(error)
      return false
    } finally {
      busy.value = ''
    }
  }

  const id = () => messageId.value

  return {
    message,
    status,
    errorMessage,
    busy,
    fieldErrors,
    isOpen,
    reasonPrompt,
    load,
    assign: (assigneeId) =>
      perform(
        'assign',
        (extra) => contactInboxService.assign(id(), { assigneeId, ...extra }),
        assigneeId ? 'contactInbox.toasts.assigned' : 'contactInbox.toasts.unassigned',
      ),
    reply: (body) =>
      perform(
        'reply',
        (extra) => contactInboxService.reply(id(), { body, ...extra }),
        'contactInbox.toasts.replied',
      ),
    addNote: (body) =>
      perform(
        'note',
        (extra) => contactInboxService.addNote(id(), { body, ...extra }),
        'contactInbox.toasts.noted',
      ),
    close: () =>
      perform(
        'close',
        (extra) => contactInboxService.close(id(), extra),
        'contactInbox.toasts.closed',
      ),
    reopen: () =>
      perform(
        'reopen',
        (extra) => contactInboxService.reopen(id(), extra),
        'contactInbox.toasts.reopened',
      ),
  }
}
