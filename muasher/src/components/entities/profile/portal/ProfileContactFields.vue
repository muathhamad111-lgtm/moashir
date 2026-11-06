<script setup>
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldCheckbox from '@/components/form/FieldCheckbox.vue'

const { t } = useI18n()
</script>

<template>
  <div class="profile-fields">
    <fieldset class="entity-form__group">
      <legend class="entity-form__legend">{{ t('portal.profile.sections.contact') }}</legend>
      <div class="entity-form__grid">
        <FieldInput
          name="phone"
          type="tel"
          input-dir="ltr"
          inputmode="tel"
          autocomplete="tel"
          :label="t('entities.fields.phone')"
          :hint="t('entities.hints.phone')"
        />
        <FieldInput
          name="websiteUrl"
          type="url"
          input-dir="ltr"
          inputmode="url"
          autocomplete="url"
          :label="t('portal.profile.fields.websiteUrl')"
          :hint="t('portal.profile.hints.websiteUrl')"
        />
      </div>
    </fieldset>
    <fieldset class="entity-form__group">
      <legend class="entity-form__legend">{{ t('entities.sections.communication') }}</legend>
      <FieldCheckbox name="marketingConsent" :label="t('entities.consent.marketing')" />
      <p class="entity-form__hint">{{ t('entities.hints.marketingConsent') }}</p>
    </fieldset>
  </div>
</template>
