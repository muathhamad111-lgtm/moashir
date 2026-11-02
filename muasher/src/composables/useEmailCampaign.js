import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { messagingService } from '@/services/messagingService'

const INDIVIDUAL_SEGMENT = 'individual'

function emptyAudience() {
  return { segment: '', recipientEntityId: null }
}

export function useEmailCampaign() {
  const { t } = useI18n()
  const toast = useToast()
  const segments = ref([])
  const recipients = ref([])
  const recipientsLoading = ref(false)
  const compose = reactive({ subject: '', body: '', audience: emptyAudience() })
  const errors = reactive({ subject: '', segment: '', recipient: '' })
  const sending = ref(false)

  async function loadSegments() {
    try {
      segments.value = (await messagingService.listSegments()).data ?? []
    } catch {
      segments.value = []
    }
  }

  async function searchRecipients(query) {
    recipientsLoading.value = true
    try {
      recipients.value = (await messagingService.searchRecipients({ search: query })).data ?? []
    } catch {
      recipients.value = []
    } finally {
      recipientsLoading.value = false
    }
  }

  const needsRecipient = computed(() => compose.audience.segment === INDIVIDUAL_SEGMENT && !compose.audience.recipientEntityId)
  const valid = computed(() => Boolean(compose.subject.trim()) && Boolean(compose.audience.segment) && !needsRecipient.value)

  function validate() {
    errors.subject = compose.subject.trim() ? '' : t('validation.required')
    errors.segment = compose.audience.segment ? '' : t('settings.email.segmentRequired')
    errors.recipient = needsRecipient.value ? t('settings.email.recipientRequired') : ''
    return valid.value
  }

  async function send() {
    if (!validate() || sending.value) return
    sending.value = true
    try {
      await messagingService.sendEmailCampaign({
        subject: compose.subject.trim(),
        body: compose.body,
        segment: compose.audience.segment,
        recipientEntityId: compose.audience.recipientEntityId,
      })
      toast.success(t('settings.email.campaignQueued'))
      Object.assign(compose, { subject: '', body: '', audience: emptyAudience() })
    } catch (error) {
      toast.error(error?.normalized?.message || t('settings.email.campaignFailed'))
    } finally {
      sending.value = false
    }
  }

  return { segments, recipients, recipientsLoading, compose, errors, sending, valid, loadSegments, searchRecipients, send }
}
