<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import AppMenu from '@/components/ui/AppMenu.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import CityForm from '@/components/entities/admin/CityForm.vue'
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
  regions,
  editor,
  regionFilterOptions,
  activeOptions,
  regionName,
  openEditor,
  toggle,
  afterSave,
} = useCitiesAdmin()
const { search } = filters

const columns = computed(() =>
  ['name', 'code', 'region', 'sortOrder', 'status', 'actions'].map((key) => ({
    key,
    label: t(`entities.cities.columns.${key}`),
  })),
)

function cityActions(city) {
  return [
    { key: 'edit', label: t('entities.cities.edit'), icon: 'pen' },
    city.isActive
      ? {
          key: 'toggle',
          label: t('entities.cities.deactivate'),
          icon: 'eye-slash',
          variant: 'danger',
        }
      : { key: 'toggle', label: t('entities.cities.activate'), icon: 'eye' },
  ]
}

function onAction(key, city) {
  if (key === 'edit') return openEditor(city)
  return toggle(city)
}
</script>

<template>
  <section class="entities-admin">
    <header class="entities-admin__header">
      <div>
        <h1 class="entities-admin__title">{{ t('entities.cities.title') }}</h1>
        <p class="entities-admin__subtitle">{{ t('entities.cities.subtitle') }}</p>
      </div>
    </header>

    <ListToolbar
      v-model:search="search"
      :search-placeholder="t('entities.cities.searchPlaceholder')"
    >
      <template #filters>
        <AppSelect
          variant="filter"
          :model-value="filters.filters.regionId"
          :options="regionFilterOptions"
          :label="t('entities.filters.region')"
          @update:model-value="filters.setFilter('regionId', $event)"
        />
        <AppSelect
          variant="filter"
          :model-value="filters.filters.active"
          :options="activeOptions"
          :label="t('entities.filters.status')"
          @update:model-value="filters.setFilter('active', $event)"
        />
      </template>
      <template #actions>
        <BaseButton variant="primary" size="sm" icon="plus" @click="openEditor()">{{
          t('entities.cities.add')
        }}</BaseButton>
      </template>
    </ListToolbar>

    <DataTable
      :columns="columns"
      :items="items"
      :status="status"
      :error-message="errorMessage"
      :empty-title="t('entities.cities.emptyTitle')"
      @retry="load"
    >
      <template #cell-name="{ item }">{{ localizedText(item.name, locale) }}</template>
      <template #cell-code="{ item }"
        ><span class="u-ltr">{{ item.code }}</span></template
      >
      <template #cell-region="{ item }">{{ regionName(item.regionId) }}</template>
      <template #cell-status="{ item }"
        ><StatusBadge :status="item.isActive ? 'active' : 'inactive'"
      /></template>
      <template #cell-actions="{ item }">
        <AppMenu
          :actions="cityActions(item)"
          :label="t('components.actions.menuFor', { name: localizedText(item.name, locale) })"
          @select="onAction($event, item)"
        />
      </template>
    </DataTable>

    <Pagination
      v-if="status === 'success' && paginator.pagination.lastPage > 1"
      :page="paginator.pagination.page"
      :last-page="paginator.pagination.lastPage"
      @update:page="paginator.setPage($event)"
    />

    <CityForm
      :key="editor.city?.id ?? 'new'"
      v-model="editor.open"
      :city="editor.city"
      :region-options="regions.regionOptions.value"
      @saved="afterSave"
    />
  </section>
</template>
