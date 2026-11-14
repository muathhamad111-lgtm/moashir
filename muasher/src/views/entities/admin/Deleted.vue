<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import FilterToolbar from '@/components/data-list/FilterToolbar.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import EntityListFilters from '@/components/entities/admin/EntityListFilters.vue'
import EntityTable from '@/components/entities/admin/EntityTable.vue'
import EntityActionsHost from '@/components/entities/admin/EntityActionsHost.vue'
import { useEntityList } from '@/composables/useEntityList'
import { useRegions } from '@/composables/useRegions'
import { useFilterDraft } from '@/composables/useFilterDraft'
import { ENTITY_ACTIONS } from '@/composables/useEntityActions'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const router = useRouter()
const regions = useRegions()
const { items, status, errorMessage, load, filters, paginator } = useEntityList({ deleted: true })
const actionsHost = ref(null)
const { search } = filters
const filterDraft = useFilterDraft(filters)

function onAction(key, entity) {
  if (key === ENTITY_ACTIONS.VIEW)
    return router.push({ name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: entity.id } })
  return actionsHost.value.run(key, entity)
}

onMounted(regions.load)
</script>

<template>
  <section class="entities-admin entities-admin--deleted">
    <header class="entities-admin__header">
      <div>
        <h1 class="entities-admin__title">{{ t('entities.deleted.title') }}</h1>
        <p class="entities-admin__subtitle">{{ t('entities.deleted.subtitle') }}</p>
      </div>
    </header>

    <aside class="entities-admin__notice" role="note">
      <AppIcon name="snowflake" />
      <p>{{ t('entities.deleted.frozenNotice') }}</p>
    </aside>

    <FilterToolbar
      v-model:search="search"
      :search-placeholder="t('entities.admin.searchPlaceholder')"
      :active-count="filterDraft.activeCount.value"
      @open-filters="filterDraft.load"
      @apply="filterDraft.apply"
      @reset="filterDraft.resetDraft"
      @clear="filterDraft.clear"
    >
      <template #filters>
        <EntityListFilters
          :filters="filterDraft.draft"
          :region-options="regions.regionOptions.value"
        />
      </template>
    </FilterToolbar>

    <EntityTable
      :items="items"
      :status="status"
      :error-message="errorMessage"
      deleted
      :empty-title="t('entities.deleted.emptyTitle')"
      :empty-message="t('entities.deleted.emptyMessage')"
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
