<script setup>
import { useI18n } from 'vue-i18n'
import GapClassChip from './GapClassChip.vue'
import AssessorQuote from './AssessorQuote.vue'
import InsightPercent from './InsightPercent.vue'
import { GAP_CLASSES } from '@/constants/insights'
import { itemKey } from '@/utils/insights'
import { localizedText } from '@/utils/localizedText'

defineProps({
  titleKey: { type: String, required: true },
  items: { type: Array, required: true },
  quoteKind: { type: String, required: true },
})

const { t, locale } = useI18n()
</script>

<template>
  <section class="executive-highlights">
    <h4 class="executive-highlights__title">{{ t(titleKey) }}</h4>
    <p v-if="!items.length" class="executive-highlights__empty">
      {{ t('insights.board.noItems') }}
    </p>
    <ul v-else class="executive-highlights__list">
      <li v-for="item in items" :key="itemKey(item)" class="executive-highlights__item">
        <div class="executive-highlights__head">
          <span class="executive-highlights__name">
            <bdi dir="ltr">{{ item.subCode }}</bdi> · {{ localizedText(item.subName, locale) }}
          </span>
          <GapClassChip v-if="item.class !== GAP_CLASSES.STRENGTH" :gap-class="item.class" />
          <InsightPercent :value="item.percent" />
        </div>
        <AssessorQuote v-if="item.quote" :kind="quoteKind" :text="item.quote" />
        <p v-else class="gap-item__none">{{ t('insights.assessor.none') }}</p>
      </li>
    </ul>
  </section>
</template>
