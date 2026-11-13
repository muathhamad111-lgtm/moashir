<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { localizedText } from '@/utils/localizedText'
import GapClassChip from './GapClassChip.vue'
import AssessorQuote from './AssessorQuote.vue'
import InsightPercent from './InsightPercent.vue'
import InsightFigure from './InsightFigure.vue'

const props = defineProps({
  item: { type: Object, required: true },
})

const { t, locale } = useI18n()
const strengths = computed(() => props.item.assessor?.strengths ?? '')
const improvements = computed(() => props.item.assessor?.improvementPoints ?? '')
</script>

<template>
  <article class="gap-item" :data-sub="item.subCode" data-testid="gap-item">
    <header class="gap-item__header">
      <div class="gap-item__names">
        <p class="gap-item__main">{{ localizedText(item.mainName, locale) }}</p>
        <h4 class="gap-item__title">
          <bdi dir="ltr">{{ item.subCode }}</bdi> · {{ localizedText(item.subName, locale) }}
        </h4>
      </div>
      <InsightPercent class="gap-item__percent" :value="item.percent" />
    </header>
    <div class="gap-item__facts">
      <GapClassChip :gap-class="item.class" />
      <p class="gap-item__figures">
        <i18n-t keypath="insights.strengths.earned" tag="span">
          <template #earned><InsightFigure :value="item.earned" /></template>
          <template #weight><InsightFigure :value="item.weight" /></template>
        </i18n-t>
        ·
        <i18n-t keypath="insights.strengths.lost" tag="span">
          <template #value><InsightFigure :value="item.lostPoints" /></template>
        </i18n-t>
      </p>
    </div>
    <AssessorQuote v-if="strengths" kind="strengths" :text="strengths" />
    <AssessorQuote v-if="improvements" kind="improvements" :text="improvements" />
    <p v-if="!strengths && !improvements" class="gap-item__none">
      {{ t('insights.assessor.none') }}
    </p>
  </article>
</template>
