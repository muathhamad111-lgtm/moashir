<script setup>
import { useI18n } from 'vue-i18n'
import { useField } from 'vee-validate'
import FieldInput from '@/components/form/FieldInput.vue'
import LocalizedFields from './LocalizedFields.vue'
import ContentImageField from './ContentImageField.vue'
import { CMS_LOGO_RULES } from '@/constants/cms'

defineProps({
  upload: { type: Function, required: true },
  disabled: { type: Boolean, default: false },
})

const { t } = useI18n()
const { value: logo, errorMessage: logoError } = useField('image')
</script>

<template>
  <div class="cms-form">
    <fieldset class="cms-form__group">
      <legend class="cms-form__legend">{{ t('cms.partners.group') }}</legend>
      <LocalizedFields name="title" :label="t('cms.partners.name')" required />
      <ContentImageField
        v-model="logo"
        :upload="upload"
        :label="t('cms.partners.logo')"
        name="partnerLogo"
        :rules="CMS_LOGO_RULES"
        :hint="t('cms.partners.logoHint')"
        :field-error="logoError"
        required
        :disabled="disabled"
      />
      <FieldInput
        name="ctaUrl"
        type="url"
        :label="t('cms.partners.link')"
        :hint="t('cms.partners.linkHint')"
        input-dir="ltr"
      />
    </fieldset>
  </div>
</template>
