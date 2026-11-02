<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import StatsCards from '@/components/data-list/StatsCards.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import EntityListFilters from '@/components/entities/admin/EntityListFilters.vue'
import EntityTable from '@/components/entities/admin/EntityTable.vue'
import EntityActionsHost from '@/components/entities/admin/EntityActionsHost.vue'
import { useEntityList } from '@/composables/useEntityList'
import { useEntityExport } from '@/composables/useEntityExport'
import { useRegions } from '@/composables/useRegions'
import { usePermissions } from '@/composables/usePermissions'
import { ENTITY_ACTIONS } from '@/composables/useEntityActions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermissions()
const regions = useRegions()
const { items, status, errorMessage, load, filters, paginator, stats, exportParams } = useEntityList()
const { exporting, exportCsv } = useEntityExport()
const actionsHost = ref(null)
const { search } = filters

const fullRecords = computed(() => items.value.some((item) => 'entityType' in item))

const statCards = computed(() => [
  { id: 'total', icon: 'fa-solid fa-building-ngo', value: stats.value?.total ?? 0, label: t('entities.stats.total') },
  { id: 'active', icon: 'fa-solid fa-circle-check', value: stats.value?.active ?? 0, label: t('entities.stats.active') },
  {
    id: 'pending',
    icon: 'fa-solid fa-hourglass-half',
    value: stats.value?.pendingVerification ?? 0,
    label: t('entities.stats.pendingVerification'),
  },
  { id: 'deactivated', icon: 'fa-solid fa-ban', value: stats.value?.deactivated ?? 0, label: t('entities.stats.deactivated') },
])

function onAction(key, entity) {
  if (key === ENTITY_ACTIONS.VIEW) return router.push({ name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: entity.id } })
  return actionsHost.value.run(key, entity)
}

onMounted(regions.load)
</script>

<template>
  <section class="entities-admin">
    <header class="entities-admin__header">
      <div>
        <h1 class="entities-admin__title">{{ t('entities.admin.title') }}</h1>
        <p class="entities-admin__subtitle">{{ t('entities.admin.subtitle') }}</p>
      </div>
      <div class="entities-admin__links">
        <BaseButton v-if="can(PERMISSIONS.ENTITIES_DELETE)" variant="ghost" size="sm" icon="fa-solid fa-trash-can-arrow-up" @click="router.push({ name: ROUTE_NAMES.ADMIN_ENTITIES_DELETED })">
          {{ t('nav.deletedEntities') }}
        </BaseButton>
      </div>
    </header>

    <StatsCards :stats="statCards" :loading="status === 'loading' && !stats" />

    <ListToolbar v-model:search="search" :search-placeholder="t('entities.admin.searchPlaceholder')">
      <template #filters>
        <EntityListFilters :filters="filters" :region-options="regions.regionOptions.value" :full="fullRecords || status !== 'success'" />
      </template>
      <template v-if="can(PERMISSIONS.ENTITIES_EXPORT)" #actions>
        <BaseButton variant="secondary" size="sm" icon="fa-solid fa-file-csv" :loading="exporting" @click="exportCsv(exportParams)">
          {{ t('entities.admin.export') }}
        </BaseButton>
      </template>
    </ListToolbar>

    <EntityTable
      :items="items"
      :status="status"
      :error-message="errorMessage"
      :full="fullRecords"
      :empty-title="t('entities.admin.emptyTitle')"
      :empty-message="t('entities.admin.emptyMessage')"
      @retry="load"
      @action="onAction"
    />

    <Pagination
      v-if="status === 'success' && paginator.pagination.lastPage > 1"
      :page="paginator.pagination.page"
      :last-page="paginator.pagination.lastPage"
      @update:page="paginator.setPage($event)"
    />

    <EntityActionsHost ref="actionsHost" @done="load" />
  </section>
</template>
