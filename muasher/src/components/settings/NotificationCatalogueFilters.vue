<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppSelect from '@/components/form/AppSelect.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import { NOTIFICATION_AUDIENCE_KINDS } from '@/constants/notificationCatalogue'

const props = defineProps({
  filters: { type: Object, required: true },
})

const emit = defineEmits(['change'])
const { t } = useI18n()

const audienceOptions = computed(() => [
  { value: '', label: t('dataList.filter.all') },
  ...NOTIFICATION_AUDIENCE_KINDS.map((value) => ({
    value,
    label: t(`settings.notifications.catalogue.audienceKinds.${value}`),
  })),
])

function update(key, value) {
  emit('change', { [key]: value })
}

const filterState = { filters: props.filters, defaults: { audience: '' }, setFilter: update }
</script>

<template>
  <ListToolbar
    class="notification-catalogue__toolbar"
    :search="filters.search"
    :search-placeholder="t('settings.notifications.catalogue.search')"
    :filter-state="filterState"
    @update:search="update('search', $event)"
  >
    <template #actions>
      <slot name="actions" />
    </template>
    <template #filters="{ draft }">
      <AppSelect
        :label="t('settings.notifications.catalogue.audienceFilter')"
        :model-value="draft.filters.audience"
        :options="audienceOptions"
        @update:model-value="draft.setFilter('audience', $event)"
      />
    </template>
  </ListToolbar>
</template>
