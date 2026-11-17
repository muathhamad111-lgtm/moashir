<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppSelect from '@/components/form/AppSelect.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import {
  NOTIFICATION_AUDIENCES,
  NOTIFICATION_CATEGORIES,
  NOTIFICATION_MODULES,
} from '@/constants/notificationCatalogue'

const props = defineProps({
  modelValue: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

function optionsOf(values, labelOf) {
  return [
    { value: '', label: t('dataList.filter.all') },
    ...values.map((value) => ({ value, label: labelOf(value) })),
  ]
}

const moduleOptions = computed(() =>
  optionsOf(NOTIFICATION_MODULES, (value) => t(`notifications.modules.${value}`)),
)
const categoryOptions = computed(() =>
  optionsOf(NOTIFICATION_CATEGORIES, (value) =>
    t(`settings.notifications.catalogue.categories.${value}`),
  ),
)
const audienceOptions = computed(() =>
  optionsOf(NOTIFICATION_AUDIENCES, (value) =>
    t(`settings.notifications.catalogue.audiences.${value}`),
  ),
)
function update(key, value) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}

const filterState = { filters: props.modelValue, setFilter: update }
</script>

<template>
  <ListToolbar
    class="notification-catalogue__filters"
    :searchable="false"
    :filter-state="filterState"
  >
    <template #filters="{ draft }">
      <AppSelect
        :label="t('settings.notifications.catalogue.columns.module')"
        :model-value="draft.filters.module"
        :options="moduleOptions"
        @update:model-value="draft.setFilter('module', $event)"
      />
      <AppSelect
        :label="t('settings.notifications.catalogue.columns.category')"
        :model-value="draft.filters.category"
        :options="categoryOptions"
        @update:model-value="draft.setFilter('category', $event)"
      />
      <AppSelect
        :label="t('settings.notifications.catalogue.audienceFilter')"
        :model-value="draft.filters.audience"
        :options="audienceOptions"
        @update:model-value="draft.setFilter('audience', $event)"
      />
    </template>
  </ListToolbar>
</template>
