<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseSelect from '@/components/form/BaseSelect.vue'
import { AWARD_RANKS, RANK_KEYS, RANK_NONE } from '@/constants/committee'

defineProps({
  rank: { type: Number, default: null },
  name: { type: String, required: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['change'])
const { t } = useI18n()
const options = computed(() => [
  { value: RANK_NONE, label: t('award.results.rank.none') },
  ...AWARD_RANKS.map((value) => ({
    value: String(value),
    label: t(`award.results.rank.${RANK_KEYS[value]}`),
  })),
])
</script>

<template>
  <BaseSelect
    :model-value="rank === null ? RANK_NONE : String(rank)"
    :name="name"
    :label="t('award.results.rank.label')"
    :options="options"
    :disabled="disabled"
    @update:model-value="emit('change', $event)"
  />
</template>
