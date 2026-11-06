<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { rangeValueText } from '@/utils/percentText'

const props = defineProps({
  range: { type: Object, required: true },
})

const { n, t } = useI18n()
const value = computed(() => rangeValueText(n, t, props.range))
const isOpenEnded = computed(() => props.range.max === null)
</script>

<template>
  <span class="level-range">
    <bdi class="level-range__value" dir="ltr">{{ value }}</bdi>
    <template v-if="isOpenEnded">{{ ' ' }}{{ t('public.home.levels.andAbove') }}</template>
  </span>
</template>
