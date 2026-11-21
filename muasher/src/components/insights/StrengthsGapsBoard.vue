<script setup>
import { ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import GapColumn from './GapColumn.vue'
import { GAP_COLUMNS, GAP_CLASSES } from '@/constants/insights'

defineProps({
  items: { type: Object, required: true },
  rules: { type: Object, required: true },
  counts: { type: Object, required: true },
})

const { t, n } = useI18n()
const baseId = useId()
const active = ref(GAP_CLASSES.STRENGTH)

function columnId(gapClass) {
  return `${baseId}-${gapClass}`
}

function tabId(gapClass) {
  return `${baseId}-tab-${gapClass}`
}
</script>

<template>
  <div class="sg-board">
    <div class="sg-board__tabs" role="tablist" :aria-label="t('insights.strengths.columnsLabel')">
      <button
        v-for="column in GAP_COLUMNS"
        :id="tabId(column.gapClass)"
        :key="column.gapClass"
        type="button"
        role="tab"
        class="sg-board__tab"
        :class="{ 'is-active': active === column.gapClass }"
        :aria-selected="active === column.gapClass"
        :aria-controls="columnId(column.gapClass)"
        @click="active = column.gapClass"
      >
        {{ t(`insights.strengths.tabs.${column.gapClass}`) }}
        <bdi dir="ltr">{{ n(counts[column.countKey] ?? 0) }}</bdi>
      </button>
    </div>
    <div class="sg-board__columns">
      <GapColumn
        v-for="column in GAP_COLUMNS"
        :id="columnId(column.gapClass)"
        :key="column.gapClass"
        :gap-class="column.gapClass"
        :items="items[column.itemsKey] ?? []"
        :rules="rules"
        :current="active === column.gapClass"
      />
    </div>
  </div>
</template>
