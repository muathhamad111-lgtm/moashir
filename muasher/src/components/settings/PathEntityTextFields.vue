<script setup>
import { useI18n } from 'vue-i18n'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import { PATH_ENTITY_TEXTS, PATH_TEXT_MAX } from '@/constants/paths'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const LOCALES = ['ar', 'en']
</script>

<template>
  <fieldset class="settings-form__group">
    <legend class="settings-form__legend">{{ t('settings.paths.textsTitle') }}</legend>
    <p class="settings-form__note" role="note">
      <AppIcon name="info-circle" />
      {{ t('settings.paths.textsNote', { max: PATH_TEXT_MAX }) }}
    </p>
    <div v-for="key in PATH_ENTITY_TEXTS" :key="key" class="path-texts__item">
      <p class="path-texts__title">{{ t(`settings.paths.texts.${key}`) }}</p>
      <div class="settings-form__grid">
        <FieldTextarea
          v-for="language in LOCALES"
          :key="language"
          :name="`entityTexts.${key}.${language}`"
          :label="t(`settings.paths.textLanguages.${language}`)"
          :rows="4"
        />
      </div>
    </div>
  </fieldset>
</template>
