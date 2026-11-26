import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { contactMessagesService } from '@/services/contactMessagesService'
import { MESSAGE_REPLY_LIMITS } from '@/constants/messages'
import { logger } from '@/utils/logger'

const CLOSED = 'contact.message_closed'
const RATE_LIMITED = 429

export function useMessageReply({ thread, onReplied, onClosed }) {
  const { t } = useI18n()
  const draft = ref('')
  const sending = ref(false)
  const error = ref('')

  const length = computed(() => draft.value.trim().length)

  function validationError() {
    if (length.value < MESSAGE_REPLY_LIMITS.min)
      return t('validation.minChars', { min: MESSAGE_REPLY_LIMITS.min })
    if (length.value > MESSAGE_REPLY_LIMITS.max)
      return t('validation.maxChars', { max: MESSAGE_REPLY_LIMITS.max })
    return ''
  }

  function rejectionMessage(normalized) {
    if (normalized.status === RATE_LIMITED) return t('portal.messages.reply.rateLimited')
    const field = normalized.fieldErrors?.body
    if (field) return Array.isArray(field) ? field[0] : field
    return normalized.message || t('portal.messages.reply.failed')
  }

  async function send() {
    if (sending.value || !thread.value) return
    error.value = validationError()
    if (error.value) return
    sending.value = true
    logger.breadcrumb('form', 'contact.reply', { length: length.value })
    try {
      const { data } = await contactMessagesService.reply(thread.value.id, draft.value.trim())
      draft.value = ''
      onReplied(data)
    } catch (failure) {
      const normalized = failure?.normalized ?? {}
      if (normalized.code === CLOSED) onClosed()
      else error.value = rejectionMessage(normalized)
    } finally {
      sending.value = false
    }
  }

  return { draft, sending, error, send }
}
