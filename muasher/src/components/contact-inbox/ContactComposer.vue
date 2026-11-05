<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { CONTACT_COMPOSER_MODES, CONTACT_INBOX_LIMITS } from '@/constants/contactInbox'

const props = defineProps({
  canReply: { type: Boolean, default: true },
  busy: { type: String, default: '' },
  serverError: { type: String, default: '' },
})

const emit = defineEmits(['reply', 'note'])
const { t } = useI18n()
const mode = ref(props.canReply ? CONTACT_COMPOSER_MODES.REPLY : CONTACT_COMPOSER_MODES.NOTE)
const body = ref('')
const localError = ref('')

const modes = computed(() =>
  Object.values(CONTACT_COMPOSER_MODES).filter(
    (value) => value !== CONTACT_COMPOSER_MODES.REPLY || props.canReply,
  ),
)
const isNote = computed(() => mode.value === CONTACT_COMPOSER_MODES.NOTE)
const error = computed(() => localError.value || props.serverError)

function validate(text) {
  if (text.length < CONTACT_INBOX_LIMITS.bodyMin)
    return t('contactInbox.composer.errors.min', { min: CONTACT_INBOX_LIMITS.bodyMin })
  if (text.length > CONTACT_INBOX_LIMITS.bodyMax)
    return t('contactInbox.composer.errors.max', { max: CONTACT_INBOX_LIMITS.bodyMax })
  return ''
}

function send() {
  const text = body.value.trim()
  localError.value = validate(text)
  if (localError.value) return
  emit(isNote.value ? 'note' : 'reply', { body: text, done: () => (body.value = '') })
}
</script>

<template>
  <form class="contact-composer" novalidate @submit.prevent="send">
    <fieldset class="contact-composer__modes">
      <legend class="contact-composer__legend">{{ t('contactInbox.composer.legend') }}</legend>
      <label v-for="value in modes" :key="value" class="contact-composer__mode">
        <input v-model="mode" type="radio" name="composer-mode" :value="value" />
        <span>{{ t(`contactInbox.composer.modes.${value}`) }}</span>
      </label>
    </fieldset>
    <BaseTextarea
      v-model="body"
      name="body"
      :label="isNote ? t('contactInbox.composer.noteLabel') : t('contactInbox.composer.replyLabel')"
      :hint="isNote ? t('contactInbox.composer.noteHint') : t('contactInbox.composer.replyHint')"
      :error="error"
      :rows="5"
      required
    />
    <div class="contact-composer__actions">
      <BaseButton
        type="submit"
        :variant="isNote ? 'secondary' : 'primary'"
        :icon="isNote ? 'pen-line' : 'send'"
        :loading="busy === (isNote ? 'note' : 'reply')"
        :disabled="Boolean(busy)"
        >{{
          isNote ? t('contactInbox.composer.addNote') : t('contactInbox.composer.send')
        }}</BaseButton
      >
    </div>
  </form>
</template>
