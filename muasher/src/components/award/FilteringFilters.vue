<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DropdownList from '@/components/ui/DropdownList.vue'
import { FILTERING_SORTS, FILTERING_STATUSES } from '@/constants/advanced'
import { statusLabelKey } from '@/utils/canonicalStatus'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  filters: { type: Object, required: true },
  sort: { type: String, required: true },
  regions: { type: Array, default: () => [] },
})

const emit = defineEmits(['filter', 'sort'])
const { t, locale } = useI18n()

const statusOptions = computed(() => [
  { value: '', label: t('filtering.filters.allStatuses') },
  ...FILTERING_STATUSES.map((value) => ({ value, label: t(statusLabelKey(value)) })),
])
const regionOptions = computed(() => [
  { value: '', label: t('filtering.filters.allRegions') },
  ...props.regions.map((region) => ({
    value: String(region.id),
    label: localizedText(region.name, locale.value),
  })),
])
const sortOptions = computed(() =>
  FILTERING_SORTS.map((value) => ({
    value,
    label: t(`filtering.sorts.${value.replace('-', 'desc_')}`),
  })),
)
</script>

<template>
  <div class="cycle-filters">
    <DropdownList
      :model-value="filters.status"
      :options="statusOptions"
      :placeholder="t('filtering.filters.status')"
      @update:model-value="emit('filter', 'status', $event)"
    />
    <DropdownList
      v-if="regions.length"
      :model-value="filters.regionId"
      :options="regionOptions"
      :placeholder="t('filtering.filters.region')"
      @update:model-value="emit('filter', 'regionId', $event)"
    />
    <DropdownList
      :model-value="sort"
      :options="sortOptions"
      :placeholder="t('filtering.filters.sort')"
      @update:model-value="emit('sort', $event)"
    />
  </div>
</template>
