<script setup>
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import TermsConsentFields from '@/components/entities/form/TermsConsentFields.vue'

const { t } = useI18n()
</script>

<template>
  <div class="entity-form__sections">
    <div class="entity-form__grid">
      <FieldInput
        name="authorizedPersonName"
        :label="t('entities.fields.authorizedPersonName')"
        autocomplete="name"
        required
      />
      <FieldInput
        name="phone"
        type="tel"
        input-dir="ltr"
        inputmode="tel"
        autocomplete="tel"
        :label="t('entities.fields.phone')"
      />
      <FieldInput
        name="officialEmail"
        type="email"
        input-dir="ltr"
        autocomplete="email"
        :label="t('entities.fields.officialEmail')"
        required
      />
      <FieldInput
        name="password"
        type="password"
        autocomplete="new-password"
        :label="t('entities.fields.password')"
        required
      />
      <FieldInput
        name="passwordConfirmation"
        type="password"
        autocomplete="new-password"
        :label="t('entities.fields.passwordConfirmation')"
        required
      />
    </div>
    <TermsConsentFields />
  </div>
</template>
