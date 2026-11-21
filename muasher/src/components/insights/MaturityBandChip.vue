<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { MATURITY_BANDS } from '@/constants/insights'
import { bandLevel } from '@/utils/insights'

const props = defineProps({
  band: { type: String, required: true },
})

const { t } = useI18n()
const level = computed(() => bandLevel(props.band))
</script>

<template>
  <span class="band-chip" :class="`band-chip--${band}`" :data-band="band">
    <span class="band-chip__glyph" aria-hidden="true">
      <span
        v-for="(step, index) in MATURITY_BANDS"
        :key="step"
        class="band-chip__bar"
        :class="{ 'is-filled': index < level }"
      ></span>
    </span>
    {{ t(`insights.bands.${band}`) }}
  </span>
</template>
