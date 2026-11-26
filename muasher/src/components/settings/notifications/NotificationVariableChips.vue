<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
  variables: { type: Array, required: true },
  required: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['insert'])
const { t, te } = useI18n()

function labelOf(name) {
  const key = `settings.notifications.texts.variableNames.${name}`
  return te(key) ? t(key) : name
}

function ariaLabelOf(name, isRequired) {
  const key = isRequired ? 'insertRequired' : 'insert'
  return t(`settings.notifications.texts.${key}`, { label: labelOf(name), name: `:${name}` })
}
</script>

<template>
  <div v-if="variables.length" class="notification-variables">
    <p class="notification-variables__label">{{ t('settings.notifications.texts.variables') }}</p>
    <ul class="notification-variables__list">
      <li v-for="name in variables" :key="name">
        <button
          type="button"
          class="notification-variables__chip"
          :class="{ 'is-required': required.includes(name) }"
          :disabled="disabled"
          :aria-label="ariaLabelOf(name, required.includes(name))"
          @click="emit('insert', name)"
        >
          <span class="notification-variables__name">{{ labelOf(name) }}</span>
          <bdi class="notification-variables__token" dir="ltr">:{{ name }}</bdi>
        </button>
      </li>
    </ul>
    <p v-if="required.length" class="notification-variables__hint">
      {{ t('settings.notifications.texts.requiredHint') }}
    </p>
  </div>
</template>
