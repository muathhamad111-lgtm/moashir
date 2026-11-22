<script setup>
import { useI18n } from 'vue-i18n'
import { localizedText } from '@/utils/localizedText'

// The descriptive indicators of a directly evaluated sub-criterion, shown as guidance beside its one
// score (operator, 2026-11-22).
defineProps({
  points: { type: Array, required: true },
  title: { type: String, required: true },
})

const { locale } = useI18n()
</script>

<template>
  <div class="indicator-guidance">
    <p class="indicator-guidance__title">{{ title }}</p>
    <ul class="indicator-guidance__list">
      <li v-for="point in points" :key="point.id">
        {{ localizedText(point.description, locale) }}
      </li>
    </ul>
  </div>
</template>
