<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import CycleStepCounter from './CycleStepCounter.vue'

defineProps({
  sections: { type: Array, required: true },
  editable: { type: Boolean, default: false },
})

const emit = defineEmits(['edit'])
const { t } = useI18n()
</script>

<template>
  <section class="cycle-summary" :aria-label="t('cycles.setup.review.title')">
    <h2 class="cycle-summary__title">
      {{ t('cycles.setup.review.title') }}
      <CycleStepCounter />
    </h2>
    <article
      v-for="section in sections"
      :key="section.key"
      class="cycle-summary__section"
      :data-step="section.key"
    >
      <header class="cycle-summary__header">
        <h3 class="cycle-summary__heading">{{ section.title }}</h3>
        <BaseButton
          v-if="editable"
          variant="ghost"
          size="sm"
          icon="pen"
          :aria-label="t('cycles.setup.review.editStep', { step: section.title })"
          @click="emit('edit', section.key)"
        >
          {{ t('cycles.setup.review.edit') }}
        </BaseButton>
      </header>
      <dl class="cycle-summary__rows">
        <div
          v-for="row in section.rows"
          :key="row.label"
          class="cycle-summary__row"
          :class="{ 'is-long': row.long, 'is-missing': row.missing }"
        >
          <dt class="cycle-summary__label">{{ row.label }}</dt>
          <dd class="cycle-summary__value">
            <AppIcon v-if="row.missing" name="warning-triangle" />
            <bdi>{{ row.value }}</bdi>
          </dd>
        </div>
      </dl>
    </article>
  </section>
</template>
