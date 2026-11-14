<script setup>
import { useI18n } from 'vue-i18n'
import FieldCheckbox from '@/components/form/FieldCheckbox.vue'
import LegalConsentNote from '@/components/legal/LegalConsentNote.vue'

const { t } = useI18n()
</script>

<template>
  <div class="entity-form__group">
    <FieldCheckbox name="termsAccepted" :label="t('entities.registration.termsAccepted')" />
    <LegalConsentNote :kinds="['terms', 'privacy']" />
    <FieldCheckbox name="marketingConsent" :label="t('entities.consent.marketing')" />
    <p class="entity-form__hint">{{ t('entities.hints.marketingConsent') }}</p>
  </div>
</template>
