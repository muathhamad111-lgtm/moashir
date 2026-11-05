<script setup>
import { toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import DropdownList from '@/components/ui/DropdownList.vue'
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
  <DropdownList
    v-if="'status' in filters.filters"
    v-bind="bind('status')"
    :options="options.statusOptions.value"
    :placeholder="t('entities.filters.status')"
  />
  <template v-if="full">
    <DropdownList
      v-bind="bind('regionId')"
      :options="options.regionFilterOptions.value"
      :placeholder="t('entities.filters.region')"
    />
    <DropdownList
      v-bind="bind('entityType')"
      :options="options.typeOptions.value"
      :placeholder="t('entities.filters.type')"
    />
    <DropdownList
      v-if="'developmentSector' in filters.filters"
      v-bind="bind('developmentSector')"
      :options="options.sectorOptions.value"
      :placeholder="t('entities.filters.sector')"
    />
    <DropdownList
      v-if="'orgSize' in filters.filters"
      v-bind="bind('orgSize')"
      :options="options.sizeOptions.value"
      :placeholder="t('entities.filters.size')"
    />
    <DropdownList
      :model-value="filters.sort.value || '-created_at'"
      :options="options.sortOptions.value"
      :placeholder="t('entities.filters.sort')"
      @update:model-value="filters.setSort($event)"
    />
  </template>
</template>
