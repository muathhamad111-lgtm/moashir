<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import EntityListFilters from '@/components/entities/admin/EntityListFilters.vue'
import EntityTable from '@/components/entities/admin/EntityTable.vue'
import EntityActionsHost from '@/components/entities/admin/EntityActionsHost.vue'
import { useEntityList } from '@/composables/useEntityList'
import { useRegions } from '@/composables/useRegions'
import { ENTITY_ACTIONS } from '@/composables/useEntityActions'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const router = useRouter()
const regions = useRegions()
const { items, status, errorMessage, load, filters, paginator } = useEntityList({ deleted: true })
const actionsHost = ref(null)
const { search } = filters

function onAction(key, entity) {
  if (key === ENTITY_ACTIONS.VIEW) return router.push({ name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: entity.id } })
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
      <i class="fa-solid fa-snowflake" aria-hidden="true"></i>
      <p>{{ t('entities.deleted.frozenNotice') }}</p>
    </aside>

    <ListToolbar v-model:search="search" :search-placeholder="t('entities.admin.searchPlaceholder')">
      <template #filters>
        <EntityListFilters :filters="filters" :region-options="regions.regionOptions.value" />
      </template>
    </ListToolbar>

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
