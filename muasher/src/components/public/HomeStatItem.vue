<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCountUp } from '@/composables/useCountUp'
import { formatCountUp, kpiNumeric } from '@/utils/kpiNumber'

const props = defineProps({
  kpi: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const { n } = useI18n()
const rootRef = ref(null)
const endValue = () => kpiNumeric(props.kpi.value) ?? 0
const { current } = useCountUp(rootRef, endValue)
const display = computed(() => formatCountUp(n, current.value, endValue()))
const unit = computed(() => props.localized(props.kpi.unit))
const name = computed(() => props.localized(props.kpi.name))
</script>

<template>
  <li ref="rootRef" class="home-stats__item">
    <bdi class="home-stats__value" dir="ltr">{{ display }}</bdi>
    <span v-if="unit" class="home-stats__unit">{{ unit }}</span>
    <span class="home-stats__name">{{ name }}</span>
  </li>
</template>
