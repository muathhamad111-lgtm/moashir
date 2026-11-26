<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
  modelValue: { type: String, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

const LANGUAGES = ['ar', 'en']
</script>

<template>
  <div
    class="notification-view-switch"
    role="group"
    :aria-label="t('settings.notifications.texts.language')"
  >
    <button
      v-for="language in LANGUAGES"
      :key="language"
      type="button"
      class="notification-view-switch__option"
      :class="{ 'is-active': modelValue === language }"
      :aria-pressed="modelValue === language"
      @click="emit('update:modelValue', language)"
    >
      {{ t(`settings.notifications.texts.${language === 'ar' ? 'arabic' : 'english'}`) }}
    </button>
  </div>
</template>
