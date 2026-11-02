<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import StatsCards from '@/components/data-list/StatsCards.vue'
import { formatNumber } from '@/utils/formatNumber'

const props = defineProps({
  items: { type: Array, default: () => [] },
})

const { t, n } = useI18n()

const stats = computed(() =>
  props.items.map((item) => ({
    id: item.id,
    icon: item.icon,
    value: formatNumber(n, Number(item.value) || 0, item.format === 'currency' ? 'currency' : 'default'),
    label: item.labelKey ? t(item.labelKey) : item.label,
  })),
)
</script>

<template>
  <StatsCards :stats="stats" />
</template>
