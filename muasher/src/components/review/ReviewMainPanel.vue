<script setup>
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import ReviewSubPanel from './ReviewSubPanel.vue'
import CriterionStepCounter from '@/components/assessment/CriterionStepCounter.vue'
import { localizedText } from '@/utils/localizedText'

defineProps({
  main: { type: Object, required: true },
  step: { type: Number, required: true },
  total: { type: Number, required: true },
  workspace: { type: Object, required: true },
})

const emit = defineEmits(['download'])
const { t, locale } = useI18n()
</script>

<template>
  <Card class="review-main">
    <template #header>
      <div class="review-main__heading">
        <CriterionStepCounter :step="step" :total="total" />
        <h2 class="review-main__title">
          {{ localizedText(main.name, locale) }}
        </h2>
      </div>
      <span class="review-main__weight">{{
        t('assessment.self.tree.mainMeta', { weight: main.weight })
      }}</span>
    </template>
    <ul class="review-main__subs">
      <ReviewSubPanel
        v-for="sub in main.subs"
        :key="sub.id"
        :sub="sub"
        :workspace="workspace"
        @download="emit('download', $event)"
      />
    </ul>
  </Card>
</template>
