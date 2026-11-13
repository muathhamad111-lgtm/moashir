<script setup>
import { useI18n } from 'vue-i18n'
import { localizedText } from '@/utils/localizedText'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  previous: { type: Object, default: null },
  next: { type: Object, default: null },
})

const emit = defineEmits(['go'])
const { t, locale } = useI18n()
</script>

<template>
  <nav class="step-nav" :aria-label="t('portal.assessments.task.stepNav')">
    <button
      v-if="previous"
      type="button"
      class="step-nav__button step-nav__button--previous"
      @click="emit('go', previous.id)"
    >
      <AppIcon name="arrow-left" />
      {{ t('portal.assessments.task.previous', { name: localizedText(previous.name, locale) }) }}
    </button>
    <button
      v-if="next"
      type="button"
      class="step-nav__button step-nav__button--next"
      @click="emit('go', next.id)"
    >
      {{ t('portal.assessments.task.next', { name: localizedText(next.name, locale) }) }}
      <AppIcon name="arrow-right" />
    </button>
  </nav>
</template>
