<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import WidgetBars from './widgets/WidgetBars.vue'
import WidgetTable from './widgets/WidgetTable.vue'
import WidgetFacts from './widgets/WidgetFacts.vue'
import WidgetCriteria from './widgets/WidgetCriteria.vue'
import WidgetTimeline from './widgets/WidgetTimeline.vue'
import { WIDGET_KINDS, widgetDefinition } from '@/constants/dashboardWidgets'

const RENDERERS = {
  [WIDGET_KINDS.BARS]: WidgetBars,
  [WIDGET_KINDS.TABLE]: WidgetTable,
  [WIDGET_KINDS.FACTS]: WidgetFacts,
  [WIDGET_KINDS.CRITERIA]: WidgetCriteria,
  [WIDGET_KINDS.TIMELINE]: WidgetTimeline,
}

const props = defineProps({
  board: { type: String, required: true },
  widget: { type: Object, required: true },
})

const { t, te } = useI18n()
const headingId = useId()
const definition = computed(() => widgetDefinition(props.board, props.widget.key))
const renderer = computed(() => RENDERERS[definition.value?.kind] ?? null)
const title = computed(() => {
  const key = `dashboard.widgets.titles.${props.widget.key}`
  return te(key) ? t(key) : props.widget.key
})
</script>

<template>
  <section
    v-if="renderer"
    class="dashboard-widget"
    :class="`dashboard-widget--${definition.kind}`"
    :aria-labelledby="headingId"
    :data-widget="widget.key"
  >
    <h3 :id="headingId" class="dashboard-widget__title">{{ title }}</h3>
    <component :is="renderer" :definition="definition" :data="widget.data" :caption="title" />
  </section>
</template>
