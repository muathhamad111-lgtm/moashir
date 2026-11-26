<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import LevelRibbon from '@/components/level/LevelRibbon.vue'
import { tierOfLevel } from '@/utils/levelTier'

// A completed request's result as the same green ribbon the entity record shows its level in: the level
// this request earned, its certificate and the percentage.
const props = defineProps({
  record: { type: Object, required: true },
})

const { t } = useI18n()
const tier = computed(() => tierOfLevel(props.record.level))
const certificate = computed(() => ({
  ...props.record.certificate,
  percent: props.record.result?.percent ?? null,
}))
</script>

<template>
  <LevelRibbon
    :tier="tier"
    :certificate="certificate"
    :eyebrow="t('requests.detail.resultEyebrow')"
  />
</template>
