<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCountUp } from '@/composables/useCountUp'
import { formatCountUp, kpiNumeric } from '@/utils/kpiNumber'
import { useLandingText } from '@/composables/landing/useLandingText'

const props = defineProps({
  kpi: { type: Object, required: true },
})

const { n } = useI18n()
const { text } = useLandingText()
const rootRef = ref(null)
const endValue = () => kpiNumeric(props.kpi.value) ?? 0
const { current } = useCountUp(rootRef, endValue)
const display = computed(() => formatCountUp(n, current.value, endValue()))
const unit = computed(() => text(props.kpi.unit))
</script>

<template>
  <li ref="rootRef" class="lp-stat">
    <b dir="ltr">
      <bdi>{{ display }}</bdi>
      <small v-if="unit">{{ unit }}</small>
    </b>
    <span>{{ text(kpi.name) }}</span>
  </li>
</template>
