<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import { useCitiesAdmin } from '@/composables/useCitiesAdmin'
import { localizedText } from '@/utils/localizedText'

const { t, locale } = useI18n()
const {
  items,
  status,
  errorMessage,
  load,
  filters,
  paginator,
  regionFilterOptions,
  activeOptions,
  regionName,
  toggle,
} = useCitiesAdmin()
const { search } = filters

const columns = computed(() =>
  ['name', 'region', 'status'].map((key) => ({
    key,
    label: t(`entities.cities.columns.${key}`),
  })),
)
</script>

<template>
  <section class="entities-admin">
    <header class="entities-admin__header entities-admin__header--toolbar">
      <h1 class="entities-admin__title">{{ t('entities.cities.title') }}</h1>
      <ListToolbar
        v-model:search="search"
        :search-placeholder="t('entities.cities.searchPlaceholder')"
        :filter-state="filters"
      >
        <template #filters="{ draft }">
          <AppSelect
            :model-value="draft.filters.regionId"
            :options="regionFilterOptions"
            :label="t('entities.filters.region')"
            @update:model-value="draft.setFilter('regionId', $event)"
          />
          <AppSelect
            :model-value="draft.filters.active"
            :options="activeOptions"
            :label="t('entities.filters.status')"
            @update:model-value="draft.setFilter('active', $event)"
          />
        </template>
      </ListToolbar>
    </header>

    <DataTable
      :columns="columns"
      :items="items"
      :status="status"
      :error-message="errorMessage"
      :empty-title="t('entities.cities.emptyTitle')"
      @retry="load"
    >
      <template #cell-name="{ item }">{{ localizedText(item.name, locale) }}</template>
      <template #cell-region="{ item }">{{ regionName(item.regionId) }}</template>
      <template #cell-status="{ item }">
        <ToggleSwitch
          :model-value="item.isActive"
          :name="`city-${item.id}-active`"
          :label="t('entities.cities.activeLabel', { name: localizedText(item.name, locale) })"
          hide-label
          @update:model-value="toggle(item)"
        />
      </template>
    </DataTable>

    <Pagination
      v-if="status === 'success' && paginator.pagination.lastPage > 1"
      :page="paginator.pagination.page"
      :last-page="paginator.pagination.lastPage"
      @update:page="paginator.setPage($event)"
    />
  </section>
</template>
