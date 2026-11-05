<script setup>
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldSelect from '@/components/form/FieldSelect.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import FieldCheckbox from '@/components/form/FieldCheckbox.vue'
import LegalConsentNote from '@/components/legal/LegalConsentNote.vue'
import { useContactForm } from '@/composables/useContactForm'
import { CONTACT_HONEYPOT, CONTACT_STATUS } from '@/constants/contact'

const MESSAGE_ROWS = 5

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  enquiryType: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'created'])
const { t } = useI18n()

const { enquiryOptions, honeypot, submitting, status, failed, onSubmit, startOver } =
  useContactForm({
    enquiryType: props.enquiryType,
    onSubmitted: (receipt) => {
      emit('created', receipt)
      emit('update:modelValue', false)
    },
  })

watch(
  () => props.modelValue,
  (open) => {
    if (open) startOver()
  },
)
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    :title="t('portal.messages.dialog.title')"
    :close-on-backdrop="!submitting"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <form
      class="new-message"
      novalidate
      :aria-busy="submitting || undefined"
      @submit.prevent="onSubmit"
    >
      <p class="new-message__intro">{{ t('portal.messages.dialog.intro') }}</p>
      <FieldSelect
        name="enquiryType"
        :label="t('public.home.contact.form.enquiryType')"
        :options="enquiryOptions"
        required
      />
      <FieldTextarea
        name="message"
        :label="t('public.home.contact.form.message')"
        :rows="MESSAGE_ROWS"
        required
      />
      <FieldInput
        name="mobile"
        type="tel"
        :label="t('public.home.contact.form.mobile')"
        :hint="t('public.home.contact.form.mobileHint')"
        autocomplete="tel"
        inputmode="tel"
        input-dir="ltr"
      />
      <FieldCheckbox name="privacyConsent" :label="t('public.home.contact.form.consent')" />
      <LegalConsentNote :kinds="['privacy']" />
      <div class="contact-form__trap" aria-hidden="true">
        <label :for="`new-message-${CONTACT_HONEYPOT}`">{{
          t('public.home.contact.form.trap')
        }}</label>
        <input
          :id="`new-message-${CONTACT_HONEYPOT}`"
          v-model="honeypot"
          type="text"
          :name="CONTACT_HONEYPOT"
          tabindex="-1"
          autocomplete="off"
        />
      </div>
      <p v-if="status === CONTACT_STATUS.rateLimited" class="new-message__alert" role="alert">
        {{ t('public.home.contact.form.rateLimited') }}
      </p>
      <p v-else-if="failed" class="new-message__alert" role="alert">
        {{ t('public.home.contact.form.failed') }}
      </p>
      <footer class="modal__footer">
        <BaseButton
          variant="secondary"
          :disabled="submitting"
          @click="emit('update:modelValue', false)"
        >
          {{ t('actions.cancel') }}
        </BaseButton>
        <BaseButton type="submit" variant="primary" icon="send" :loading="submitting">
          {{ t('portal.messages.dialog.submit') }}
        </BaseButton>
      </footer>
    </form>
  </BaseModal>
</template>
