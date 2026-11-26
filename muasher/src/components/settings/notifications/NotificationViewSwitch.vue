<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
  modelValue: { type: String, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

const VIEWS = ['byType', 'byChannel']
</script>

<template>
  <div
    class="notification-view-switch"
    role="group"
    :aria-label="t('settings.notifications.catalogue.views.label')"
  >
    <button
      v-for="view in VIEWS"
      :key="view"
      type="button"
      class="notification-view-switch__option"
      :class="{ 'is-active': modelValue === view }"
      :aria-pressed="modelValue === view"
      @click="emit('update:modelValue', view)"
    >
      {{ t(`settings.notifications.catalogue.views.${view}`) }}
    </button>
  </div>
</template>
