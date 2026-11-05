<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import StatsCards from '@/components/data-list/StatsCards.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import KpiAdminToolbar from './KpiAdminToolbar.vue'
import KpiAdminTable from './KpiAdminTable.vue'
import KpiFormDialog from './KpiFormDialog.vue'
import KpiValuesDialog from './KpiValuesDialog.vue'
import KpiReorderDialog from './KpiReorderDialog.vue'
import { useKpisAdminList } from '@/composables/useKpisAdminList'
import { useKpiRowActions } from '@/composables/useKpiRowActions'
import { useToast } from '@/composables/useToast'
import { kpisAdminService } from '@/services/kpisAdminService'
import { KPI_DEFAULT_SORT } from '@/constants/kpis'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  types: { type: Array, default: () => [] },
  localized: { type: Function, required: true },
})

const { t } = useI18n()
const toast = useToast()
const { filters, paginator, stats, items, status, errorMessage, load } = useKpisAdminList()
const actions = useKpiRowActions({ reload: load, localized: props.localized })
const formOpen = ref(false)
const valuesOpen = ref(false)
const reorderOpen = ref(false)
const selected = ref(null)

const STAT_KEYS = {
  total: 'chart-bar',
  published: 'eye',
  unpublished: 'eye-slash',
  featured: 'star',
}
const statCards = computed(() =>
  Object.entries(STAT_KEYS).map(([id, icon]) => ({
    id,
    icon,
    value: stats.value?.[id] ?? 0,
    label: t(`kpis.admin.stats.${id}`),
  })),
)
const isFiltered = computed(
  () =>
    Boolean(filters.search.value) ||
    Object.values(filters.filters).some(Boolean) ||
    ![KPI_DEFAULT_SORT, ''].includes(filters.sort.value),
)

function open(kind, kpi = null) {
  selected.value = kpi
  if (kind === 'form') formOpen.value = true
  else valuesOpen.value = true
}

async function save(payload) {
  if (selected.value) await kpisAdminService.update(selected.value.id, payload)
  else await kpisAdminService.create(payload)
  toast.success(t(selected.value ? 'kpis.admin.toasts.updated' : 'kpis.admin.toasts.created'))
  await load()
}

const HANDLERS = {
  edit: (kpi) => open('form', kpi),
  values: (kpi) => open('values', kpi),
  publish: (kpi) => actions.togglePublished(kpi),
  feature: (kpi) => actions.toggleFeatured(kpi),
  archive: (kpi) => actions.archive(kpi),
}
</script>

<template>
  <div class="kpis-admin__panel">
    <StatsCards :stats="statCards" :loading="status === 'loading' && !stats" />
    <KpiAdminToolbar
      v-model:search="filters.search.value"
      :values="filters.filters"
      :sort="filters.sort.value"
      :categories="categories"
      :localized="localized"
      @filter="filters.setFilter"
      @sort="filters.setSort"
      @create="open('form')"
      @reorder="reorderOpen = true"
    />
    <p v-if="isFiltered && status === 'success'" class="kpis-admin__reorder-note" role="note">
      <AppIcon name="info-circle" />
      {{ t('kpis.admin.index.reorderNote') }}
    </p>
    <KpiAdminTable
      :items="items"
      :status="status"
      :error-message="errorMessage"
      :categories="categories"
      :localized="localized"
      :can-reorder="!isFiltered"
      :total="stats?.total ?? 0"
      :pending-id="actions.pendingId.value"
      @retry="load"
      @create="open('form')"
      @move="actions.move"
      @action="(key, kpi) => HANDLERS[key](kpi)"
    />
    <Pagination
      v-if="status === 'success' && paginator.pagination.lastPage > 1"
      :page="paginator.pagination.page"
      :last-page="paginator.pagination.lastPage"
      @update:page="paginator.setPage($event)"
    />
    <KpiFormDialog
      v-model="formOpen"
      :kpi="selected"
      :categories="categories"
      :types="types"
      :save="save"
    />
    <KpiValuesDialog v-model="valuesOpen" :kpi="selected" @recorded="load" />
    <KpiReorderDialog v-model="reorderOpen" :localized="localized" @saved="load" />
  </div>
</template>
