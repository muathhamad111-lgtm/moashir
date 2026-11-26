<script setup>
import { useI18n } from 'vue-i18n'
import { CONTACT_HONEYPOT, CONTACT_STATUS } from '@/constants/contact'
import { useContactForm } from '@/composables/useContactForm'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldSelect from '@/components/form/FieldSelect.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import FieldCheckbox from '@/components/form/FieldCheckbox.vue'
import LegalConsentNote from '@/components/legal/LegalConsentNote.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const MESSAGE_ROWS = 4

const { t } = useI18n()
const {
  isGuest,
  enquiryOptions,
  honeypot,
  submitting,
  status,
  reference,
  failed,
  onSubmit,
  startOver,
} = useContactForm()
</script>

<template>
  <div class="contact-form">
    <div v-if="status === CONTACT_STATUS.submitted" class="contact-form__success" role="status">
      <span class="contact-form__success-icon" aria-hidden="true">
        <AppIcon name="check-circle" />
      </span>
      <h3 class="contact-form__title">{{ t('public.home.contact.form.successTitle') }}</h3>
      <p class="contact-form__success-body">{{ t('public.home.contact.form.successBody') }}</p>
      <p class="contact-form__reference">
        <span>{{ t('public.home.contact.form.referenceLabel') }}</span>
        <bdi class="contact-form__reference-value" dir="ltr">{{ reference }}</bdi>
      </p>
      <BaseButton variant="secondary" @click="startOver">
        {{ t('public.home.contact.form.sendAnother') }}
      </BaseButton>
    </div>

    <form v-else class="contact-form__form" novalidate @submit.prevent="onSubmit">
      <h3 class="contact-form__title">{{ t('public.home.contact.form.title') }}</h3>
      <p v-if="!isGuest" class="contact-form__signed-in">
        <AppIcon name="user-circle" />
        {{ t('public.home.contact.form.signedIn') }}
      </p>
      <div class="contact-form__grid">
        <FieldInput
          name="name"
          :label="t('public.home.contact.form.name')"
          autocomplete="name"
          :required="isGuest"
          :readonly="!isGuest"
        />
        <FieldInput
          name="email"
          type="email"
          :label="t('public.home.contact.form.email')"
          autocomplete="email"
          input-dir="ltr"
          :required="isGuest"
          :readonly="!isGuest"
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
        <FieldInput
          name="organization"
          :label="t('public.home.contact.form.organization')"
          autocomplete="organization"
          :readonly="!isGuest"
        />
      </div>
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
      <FieldCheckbox name="privacyConsent" :label="t('public.home.contact.form.consent')" />
      <LegalConsentNote :kinds="['privacy']" />
      <div class="contact-form__trap" aria-hidden="true">
        <label :for="`contact-${CONTACT_HONEYPOT}`">{{ t('public.home.contact.form.trap') }}</label>
        <input
          :id="`contact-${CONTACT_HONEYPOT}`"
          v-model="honeypot"
          type="text"
          :name="CONTACT_HONEYPOT"
          tabindex="-1"
          autocomplete="off"
        />
      </div>
      <p v-if="status === CONTACT_STATUS.rateLimited" class="contact-form__alert" role="alert">
        {{ t('public.home.contact.form.rateLimited') }}
      </p>
      <p v-else-if="failed" class="contact-form__alert" role="alert">
        {{ t('public.home.contact.form.failed') }}
      </p>
      <div class="contact-form__footer">
        <BaseButton type="submit" variant="primary" size="xl" icon="send" :loading="submitting">
          {{ t('public.home.contact.form.submit') }}
        </BaseButton>
        <span class="contact-form__note">{{ t('public.home.contact.form.note') }}</span>
      </div>
    </form>
  </div>
</template>
