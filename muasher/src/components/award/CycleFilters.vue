<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DropdownList from '@/components/ui/DropdownList.vue'
import { CYCLE_SORTS, CYCLE_STATUS_LIST } from '@/constants/advanced'

defineProps({
  filters: { type: Object, required: true },
  sort: { type: String, required: true },
})

const emit = defineEmits(['filter', 'sort'])
const { t } = useI18n()

const statusOptions = computed(() => [
  { value: '', label: t('cycles.filters.allStatuses') },
  ...CYCLE_STATUS_LIST.map((value) => ({ value, label: t(`cycles.statuses.${value}`) })),
])
const sortOptions = computed(() =>
  CYCLE_SORTS.map((value) => ({ value, label: t(`cycles.sorts.${value.replace('-', 'desc_')}`) })),
)
</script>

<template>
  <div class="cycle-filters">
    <DropdownList
      :model-value="filters.status"
      :options="statusOptions"
      :placeholder="t('cycles.filters.status')"
      @update:model-value="emit('filter', 'status', $event)"
    />
    <DropdownList
      :model-value="sort"
      :options="sortOptions"
      :placeholder="t('cycles.filters.sort')"
      @update:model-value="emit('sort', $event)"
    />
  </div>
</template>
