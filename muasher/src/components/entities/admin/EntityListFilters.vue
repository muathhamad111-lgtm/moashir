<script setup>
import { toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import AppSelect from '@/components/form/AppSelect.vue'
import { useEntityFilterOptions } from '@/composables/useEntityFilterOptions'

const props = defineProps({
  filters: { type: Object, required: true },
  regionOptions: { type: Array, required: true },
  full: { type: Boolean, default: true },
})

const { t } = useI18n()
const options = useEntityFilterOptions(toRef(props, 'regionOptions'))

function bind(key) {
  return {
    modelValue: props.filters.filters[key],
    'onUpdate:modelValue': (value) => props.filters.setFilter(key, value),
  }
}
</script>

<template>
  <AppSelect
    v-if="'level' in filters.filters"
    v-bind="bind('level')"
    :options="options.levelOptions.value"
    :label="t('entities.filters.level')"
    data-testid="entity-level-filter"
  />
  <AppSelect
    v-if="'status' in filters.filters"
    v-bind="bind('status')"
    :options="options.statusOptions.value"
    :label="t('entities.filters.status')"
  />
  <template v-if="full">
    <AppSelect
      v-bind="bind('regionId')"
      :options="options.regionFilterOptions.value"
      :label="t('entities.filters.region')"
    />
    <AppSelect
      v-bind="bind('entityType')"
      :options="options.typeOptions.value"
      :label="t('entities.filters.type')"
    />
    <AppSelect
      v-if="'developmentSector' in filters.filters"
      v-bind="bind('developmentSector')"
      :options="options.sectorOptions.value"
      :label="t('entities.filters.sector')"
    />
    <AppSelect
      v-if="'orgSize' in filters.filters"
      v-bind="bind('orgSize')"
      :options="options.sizeOptions.value"
      :label="t('entities.filters.size')"
    />
    <AppSelect
      :model-value="filters.sort.value || '-created_at'"
      :options="options.sortOptions.value"
      :label="t('entities.filters.sort')"
      data-testid="entity-sort"
      @update:model-value="filters.setSort($event)"
    />
  </template>
</template>
