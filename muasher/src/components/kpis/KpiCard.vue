<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import { KPI_STATUS_VARIANTS } from '@/constants/kpiCategories'

const props = defineProps({
  kpi: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const emit = defineEmits(['activate'])

const { n, t } = useI18n()

const TOKEN_COLORS = ['primary', 'success', 'info', 'warning', 'danger']

const cardColor = computed(() =>
  TOKEN_COLORS.includes(props.kpi.cardColor)
    ? props.kpi.cardColor
    : TOKEN_COLORS.includes(props.kpi.color)
      ? props.kpi.color
      : 'primary',
)

const statusVariant = computed(() => KPI_STATUS_VARIANTS[props.kpi.status] ?? 'muted')

const statusLabel = computed(() =>
  props.kpi.status ? t(`kpis.statuses.${props.kpi.status}`) : '',
)

const progress = computed(() => {
  const target = Number(props.kpi.target) || 0
  const value = Number(props.kpi.value) || 0
  if (!target) return null
  return Math.min(100, Math.round((value / target) * 100))
})

const isInteger = computed(() => Number.isInteger(Number(props.kpi.value)))

const rootRef = ref(null)
const displayValue = ref(Number(props.kpi.value) || 0)
let observer = null
let frameId = null

function fmt(value) {
  if (typeof value !== 'number') return value
  const formatted = n(value, 'default')
  return formatted === '' ? String(value) : formatted
}

const displayNumber = computed(() => {
  const rounded = isInteger.value
    ? Math.round(displayValue.value)
    : Math.round(displayValue.value * 10) / 10
  return fmt(rounded)
})

function prefersReducedMotion() {
  return (
    typeof window !== 'undefined' &&
    typeof window.matchMedia === 'function' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches
  )
}

function animateCount() {
  const end = Number(props.kpi.value) || 0
  if (prefersReducedMotion() || typeof requestAnimationFrame !== 'function') {
    displayValue.value = end
    return
  }
  displayValue.value = 0
  const duration = 1100
  const start = performance.now()
  function step(now) {
    const elapsed = now - start
    const ratio = Math.min(1, elapsed / duration)
    const eased = 1 - Math.pow(1 - ratio, 3)
    displayValue.value = end * eased
    if (ratio < 1) {
      frameId = requestAnimationFrame(step)
    } else {
      displayValue.value = end
    }
  }
  frameId = requestAnimationFrame(step)
}

onMounted(() => {
  if (typeof IntersectionObserver !== 'function' || !rootRef.value || prefersReducedMotion()) {
    displayValue.value = Number(props.kpi.value) || 0
    return
  }
  observer = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          animateCount()
          observer.disconnect()
          observer = null
          break
        }
      }
    },
    { threshold: 0.35 },
  )
  observer.observe(rootRef.value)
})

onBeforeUnmount(() => {
  if (observer) observer.disconnect()
  if (frameId) cancelAnimationFrame(frameId)
})

function onActivate() {
  emit('activate', props.kpi)
}

function onKeydown(event) {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    onActivate()
  }
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
    @click="onActivate"
    @keydown="onKeydown"
  >
    <div class="kpi-card__head">
      <span class="kpi-card__icon" aria-hidden="true">
        <i :class="kpi.icon"></i>
      </span>
      <Badge v-if="statusLabel" :variant="statusVariant" class="kpi-card__status">
        {{ statusLabel }}
      </Badge>
    </div>

    <p class="kpi-card__value">
      <span class="kpi-card__number" aria-hidden="true">{{ displayNumber }}</span>
      <span class="kpi-card__number-sr">{{ fmt(kpi.value) }}</span>
      <span class="kpi-card__unit">{{ localized(kpi.unit) }}</span>
    </p>

    <h3 class="kpi-card__name">{{ localized(kpi.name) }}</h3>

    <div v-if="progress !== null" class="kpi-card__target">
      <div
        class="kpi-card__meter"
        role="progressbar"
        :aria-valuenow="progress"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-label="localized(kpi.name)"
      >
        <span class="kpi-card__meter-fill" :style="{ '--kpi-progress': `${progress}%` }"></span>
      </div>
      <span class="kpi-card__target-label">
        {{ $t('kpis.targetLabel', { value: fmt(kpi.target), unit: localized(kpi.unit) }) }}
      </span>
    </div>

    <div class="kpi-card__reveal" aria-hidden="true">
      <dl class="kpi-card__facts">
        <div v-if="kpi.target" class="kpi-card__fact">
          <dt class="kpi-card__fact-label">{{ $t('kpis.page.reveal.target') }}</dt>
          <dd class="kpi-card__fact-value">{{ fmt(kpi.target) }} {{ localized(kpi.unit) }}</dd>
        </div>
        <div v-if="kpi.baseline !== null && kpi.baseline !== undefined" class="kpi-card__fact">
          <dt class="kpi-card__fact-label">{{ $t('kpis.page.reveal.baseline') }}</dt>
          <dd class="kpi-card__fact-value">{{ fmt(kpi.baseline) }} {{ localized(kpi.unit) }}</dd>
        </div>
        <div v-if="kpi.lastUpdate" class="kpi-card__fact">
          <dt class="kpi-card__fact-label">{{ $t('kpis.page.reveal.lastUpdate') }}</dt>
          <dd class="kpi-card__fact-value">{{ kpi.lastUpdate }}</dd>
        </div>
      </dl>
      <p v-if="kpi.description" class="kpi-card__description">{{ localized(kpi.description) }}</p>
      <span class="kpi-card__cue">{{ $t('kpis.page.reveal.viewDetail') }}</span>
    </div>
  </article>
</template>
