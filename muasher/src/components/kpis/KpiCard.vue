<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import { DEFAULT_KPI_COLOR, KPI_COLOR_TOKENS, KPI_STATUS_VARIANTS } from '@/constants/kpis'
import { useCountUp } from '@/composables/useCountUp'
import { formatRiyadhDate } from '@/utils/datetime'
import { formatCountUp, formatKpiValue, kpiNumeric, kpiProgress } from '@/utils/kpiNumber'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  kpi: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const emit = defineEmits(['activate'])

const { t, n, locale } = useI18n()

const rootRef = ref(null)
const endValue = () => kpiNumeric(props.kpi.value) ?? 0
const { current } = useCountUp(rootRef, endValue)

const cardColor = computed(() => {
  const token = props.kpi.cardColor ?? props.kpi.cardColorToken
  return KPI_COLOR_TOKENS.includes(token) ? token : DEFAULT_KPI_COLOR
})
const statusVariant = computed(() => KPI_STATUS_VARIANTS[props.kpi.status] ?? 'muted')
const statusLabel = computed(() => (props.kpi.status ? t(`kpis.statuses.${props.kpi.status}`) : ''))
const progress = computed(() => kpiProgress(props.kpi.value, props.kpi.target))
const unit = computed(() => props.localized(props.kpi.unit))
const description = computed(() => props.localized(props.kpi.shortDescription))
const lastUpdate = computed(() => formatRiyadhDate(props.kpi.lastUpdate, locale.value))
const displayNumber = computed(() => formatCountUp(n, current.value, endValue()))

function fmt(value) {
  return formatKpiValue(n, value)
}

function onKeydown(event) {
  if (event.key !== 'Enter' && event.key !== ' ') return
  event.preventDefault()
  emit('activate', props.kpi)
}
</script>

<template>
  <article
    ref="rootRef"
    class="kpi-card"
    :class="`kpi-card--${cardColor}`"
    tabindex="0"
    role="link"
    :aria-label="localized(kpi.name)"
    @click="emit('activate', kpi)"
    @keydown="onKeydown"
  >
    <div class="kpi-card__head">
      <span class="kpi-card__icon" aria-hidden="true">
        <AppIcon :name="kpi.icon || 'chart-bar'" />
      </span>
      <Badge v-if="statusLabel" :variant="statusVariant" class="kpi-card__status">
        {{ statusLabel }}
      </Badge>
    </div>

    <p class="kpi-card__value">
      <span class="kpi-card__number" aria-hidden="true">{{ displayNumber }}</span>
      <span class="kpi-card__number-sr">{{ fmt(kpi.value) }}</span>
      <span class="kpi-card__unit">{{ unit }}</span>
    </p>

    <h3 class="kpi-card__name">{{ localized(kpi.name) }}</h3>

    <div v-if="progress !== null" class="kpi-card__target">
      <progress
        class="kpi-card__meter"
        :value="progress"
        max="100"
        :aria-label="localized(kpi.name)"
      >
        {{ progress }}
      </progress>
      <span class="kpi-card__target-label">
        {{ t('kpis.targetLabel', { value: fmt(kpi.target), unit }) }}
      </span>
    </div>

    <div class="kpi-card__reveal" aria-hidden="true">
      <dl class="kpi-card__facts">
        <div v-if="kpiNumeric(kpi.target) !== null" class="kpi-card__fact">
          <dt class="kpi-card__fact-label">{{ t('kpis.page.reveal.target') }}</dt>
          <dd class="kpi-card__fact-value">{{ fmt(kpi.target) }} {{ unit }}</dd>
        </div>
        <div v-if="kpiNumeric(kpi.baseline) !== null" class="kpi-card__fact">
          <dt class="kpi-card__fact-label">{{ t('kpis.page.reveal.baseline') }}</dt>
          <dd class="kpi-card__fact-value">{{ fmt(kpi.baseline) }} {{ unit }}</dd>
        </div>
        <div v-if="lastUpdate" class="kpi-card__fact">
          <dt class="kpi-card__fact-label">{{ t('kpis.page.reveal.lastUpdate') }}</dt>
          <dd class="kpi-card__fact-value">{{ lastUpdate }}</dd>
        </div>
      </dl>
      <p v-if="description" class="kpi-card__description">{{ description }}</p>
      <span class="kpi-card__cue">{{ t('kpis.page.reveal.viewDetail') }}</span>
    </div>
  </article>
</template>
