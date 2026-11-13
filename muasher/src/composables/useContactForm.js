import { computed, ref, watch } from 'vue'
import { useForm } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { contactMessagesService } from '@/services/contactMessagesService'
import { contactSchema } from '@/utils/contactSchema'
import { CONTACT_ENQUIRY_TYPES, CONTACT_HONEYPOT, CONTACT_STATUS } from '@/constants/contact'
import { localizedText } from '@/utils/localizedText'
import { logger } from '@/utils/logger'
import { payloadShape } from '@/utils/payloadShape'

const RATE_LIMITED = 429
const EMPTY_VALUES = Object.freeze({
  name: '',
  email: '',
  mobile: '',
  organization: '',
  enquiryType: '',
  message: '',
  privacyConsent: false,
})

function trimmed(value) {
  return String(value ?? '').trim()
}

function withoutEmpty(payload) {
  return Object.fromEntries(Object.entries(payload).filter(([, value]) => value !== ''))
}

function firstMessages(fieldErrors) {
  return Object.fromEntries(
    Object.entries(fieldErrors).map(([field, messages]) => [
      field,
      Array.isArray(messages) ? messages[0] : messages,
    ]),
  )
}

export function useContactForm({ enquiryType = '', onSubmitted = () => {} } = {}) {
  const { t, locale } = useI18n()
  const auth = useAuthStore()
  const isGuest = computed(() => !auth.isAuthenticated)
  const schema = computed(() => contactSchema(t, { isGuest: isGuest.value }))
  const startValues = { ...EMPTY_VALUES, enquiryType }
  const form = useForm({ validationSchema: schema, initialValues: { ...startValues } })
  const honeypot = ref('')
  const submitting = ref(false)
  const status = ref(CONTACT_STATUS.idle)
  const reference = ref('')
  const messageId = ref(null)
  const failed = ref(false)

  const enquiryOptions = computed(() =>
    CONTACT_ENQUIRY_TYPES.map((value) => ({
      value,
      label: t(`public.home.contact.form.enquiryTypes.${value}`),
    })),
  )

  function applyIdentity() {
    if (isGuest.value) return
    form.setFieldValue('name', auth.user?.name ?? '', false)
    form.setFieldValue('email', auth.user?.email ?? '', false)
    form.setFieldValue('organization', localizedText(auth.user?.entity?.name, locale.value), false)
  }

  function payloadOf(values) {
    const shared = {
      mobile: trimmed(values.mobile),
      enquiryType: values.enquiryType,
      message: trimmed(values.message),
      privacyConsent: values.privacyConsent === true,
    }
    const identity = isGuest.value
      ? {
          name: trimmed(values.name),
          email: trimmed(values.email),
          organization: trimmed(values.organization),
        }
      : {}
    return { ...withoutEmpty({ ...identity, ...shared }), [CONTACT_HONEYPOT]: honeypot.value }
  }

  function reject(error) {
    const normalized = error?.normalized ?? {}
    if (normalized.status === RATE_LIMITED) {
      status.value = CONTACT_STATUS.rateLimited
      return
    }
    const fieldErrors = normalized.fieldErrors ?? {}
    if (Object.keys(fieldErrors).length) form.setErrors(firstMessages(fieldErrors))
    else failed.value = true
  }

  const onSubmit = form.handleSubmit(async (values) => {
    if (submitting.value) return
    submitting.value = true
    failed.value = false
    status.value = CONTACT_STATUS.idle
    const payload = payloadOf(values)
    logger.breadcrumb('form', 'contact.submit', { fields: payloadShape(payload) })
    try {
      const envelope = await contactMessagesService.submit(payload)
      reference.value = envelope?.data?.reference ?? ''
      messageId.value = envelope?.data?.id ?? null
      status.value = CONTACT_STATUS.submitted
      onSubmitted({ reference: reference.value, id: messageId.value })
    } catch (error) {
      reject(error)
    } finally {
      submitting.value = false
    }
  })

  function startOver() {
    form.resetForm({ values: { ...startValues } })
    honeypot.value = ''
    reference.value = ''
    messageId.value = null
    failed.value = false
    status.value = CONTACT_STATUS.idle
    applyIdentity()
  }

  watch(() => auth.user, applyIdentity, { immediate: true })

  return {
    isGuest,
    enquiryOptions,
    honeypot,
    submitting,
    status,
    reference,
    messageId,
    failed,
    onSubmit,
    startOver,
  }
}
