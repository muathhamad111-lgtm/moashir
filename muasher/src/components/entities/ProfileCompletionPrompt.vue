<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

const props = defineProps({
  welcome: { type: Boolean, default: false },
  actionLabel: { type: String, default: '' },
})

const emit = defineEmits(['complete'])
const { t } = useI18n()

const title = computed(() =>
  props.welcome ? t('entities.completion.welcomeTitle') : t('entities.completion.title'),
)
const message = computed(() =>
  props.welcome ? t('entities.completion.welcomeMessage') : t('entities.completion.message'),
)
</script>

<template>
  <aside class="completion-prompt" role="status">
    <span class="completion-prompt__icon" aria-hidden="true">
      <i class="fa-solid fa-clipboard-check"></i>
    </span>
    <div class="completion-prompt__body">
      <p class="completion-prompt__title">{{ title }}</p>
      <p class="completion-prompt__message">{{ message }}</p>
    </div>
    <BaseButton variant="primary" size="sm" @click="emit('complete')">
      {{ actionLabel || t('entities.completion.action') }}
    </BaseButton>
  </aside>
</template>
