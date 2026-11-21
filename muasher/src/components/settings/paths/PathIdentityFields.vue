<script setup>
import { useI18n } from 'vue-i18n'
import LocalizedFields from '@/components/cms/LocalizedFields.vue'

const { t } = useI18n()
</script>

<template>
  <fieldset class="settings-form__group">
    <legend class="settings-form__legend">{{ t('settings.paths.description') }}</legend>
    <LocalizedFields
      name="description"
      :label="t('settings.paths.description')"
      multiline
      required
    />
  </fieldset>
</template>
