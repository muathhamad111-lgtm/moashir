<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DataListCard from '@/components/data-list/DataListCard.vue'
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
import { accessFor } from '@/constants/routeAccess'

const { t } = useI18n()
const router = useRouter()
const { can, canAccess } = usePermissions()
const canOpen = (routeName) => canAccess(accessFor(routeName))
const regions = useRegions()
const { items, status, errorMessage, load, filters, paginator, stats, exportParams } =
  useEntityList()
const { exporting, exportCsv } = useEntityExport()
const actionsHost = ref(null)
const { search } = filters

const fullRecords = computed(() => items.value.some((item) => 'entityType' in item))

const statCards = computed(() => [
  {
    id: 'total',
    icon: 'building',
    value: stats.value?.total ?? 0,
    label: t('entities.stats.total'),
  },
  {
    id: 'active',
    icon: 'check-circle',
    value: stats.value?.active ?? 0,
    label: t('entities.stats.active'),
  },
  {
    id: 'pending-review',
    icon: 'clipboard-check',
    value: stats.value?.pendingReview ?? 0,
    label: t('entities.stats.pendingReview'),
  },
  {
    id: 'pending',
    icon: 'refresh-dots',
    value: stats.value?.pendingVerification ?? 0,
    label: t('entities.stats.pendingVerification'),
  },
  {
    id: 'deactivated',
    icon: 'ban',
    value: stats.value?.deactivated ?? 0,
    label: t('entities.stats.deactivated'),
  },
])

function onAction(key, entity) {
  if (key === ENTITY_ACTIONS.VIEW)
    return router.push({ name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: entity.id } })
  return actionsHost.value.run(key, entity)
}

onMounted(regions.load)
</script>

<template>
  <section class="entities-admin">
    <header class="entities-admin__header">
      <h1 class="entities-admin__title">{{ t('entities.admin.title') }}</h1>
      <div class="entities-admin__links">
        <BaseButton
          v-if="canOpen(ROUTE_NAMES.ADMIN_ENTITY_PROFILE_CHANGES)"
          variant="secondary"
          icon="pen-line"
          :to="{ name: ROUTE_NAMES.ADMIN_ENTITY_PROFILE_CHANGES }"
        >
          {{ t('nav.entityProfileChanges') }}
        </BaseButton>
        <BaseButton
          v-if="canOpen(ROUTE_NAMES.ADMIN_ENTITY_EMAIL_CHANGES)"
          variant="secondary"
          icon="envelope"
          :to="{ name: ROUTE_NAMES.ADMIN_ENTITY_EMAIL_CHANGES }"
        >
          {{ t('nav.entityEmailChanges') }}
        </BaseButton>
        <BaseButton
          v-if="canOpen(ROUTE_NAMES.ADMIN_ENTITIES_DELETED)"
          variant="secondary"
          icon="undo"
          :to="{ name: ROUTE_NAMES.ADMIN_ENTITIES_DELETED }"
        >
          {{ t('nav.deletedEntities') }}
        </BaseButton>
      </div>
    </header>

    <StatsCards :stats="statCards" :loading="status === 'loading' && !stats" />

    <DataListCard>
      <template #toolbar>
        <ListToolbar
          v-model:search="search"
          :search-placeholder="t('entities.admin.searchPlaceholder')"
          :filter-state="filters"
        >
          <template #filters="{ draft }">
            <EntityListFilters
              :filters="draft"
              :region-options="regions.regionOptions.value"
              :full="fullRecords || status !== 'success'"
            />
          </template>
          <template v-if="can(PERMISSIONS.ENTITIES_EXPORT)" #export>
            <BaseButton
              variant="secondary"
              size="sm"
              icon="download"
              :loading="exporting"
              @click="exportCsv(exportParams)"
            >
              {{ t('entities.admin.export') }}
            </BaseButton>
          </template>
        </ListToolbar>
      </template>

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

      <template #footer>
        <Pagination
          v-if="status === 'success' && paginator.pagination.lastPage > 1"
          :page="paginator.pagination.page"
          :last-page="paginator.pagination.lastPage"
          @update:page="paginator.setPage($event)"
        />
      </template>
    </DataListCard>

    <EntityActionsHost ref="actionsHost" @done="load" />
  </section>
</template>
