<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  steps: { type: Array, required: true },
})

const emit = defineEmits(['contact'])
const { t } = useI18n()
</script>

<template>
  <div class="guide-aside">
    <section v-if="steps.length" class="guide-aside__card" aria-labelledby="guide-aside-title">
      <h2 id="guide-aside-title" class="guide-aside__title">
        <AppIcon name="lightbulb" />
        {{ t('portal.assessments.task.guide') }}
      </h2>
      <ul class="guide-aside__steps">
        <li v-for="step in steps" :key="step" class="guide-aside__step">
          <AppIcon name="check-circle" />
          <span>{{ step }}</span>
        </li>
      </ul>
    </section>
    <section class="guide-aside__card guide-aside__card--muted" aria-labelledby="guide-protected">
      <h2 id="guide-protected" class="guide-aside__title">
        <AppIcon name="shield-info" />
        {{ t('portal.assessments.task.protectedTitle') }}
      </h2>
      <p class="guide-aside__text">{{ t('portal.assessments.task.protectedText') }}</p>
    </section>
    <slot />
    <section class="guide-aside__card" aria-labelledby="guide-contact">
      <h2 id="guide-contact" class="guide-aside__title">
        {{ t('portal.assessments.task.questionTitle') }}
      </h2>
      <BaseButton variant="secondary" icon="envelope" @click="emit('contact')">
        {{ t('portal.assessments.task.questionAction') }}
      </BaseButton>
    </section>
  </div>
</template>
