<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useHead } from '@unhead/vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import ActionsDropdown from '@/components/data-list/ActionsDropdown.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Badge from '@/components/ui/Badge.vue'
import DropdownList from '@/components/ui/DropdownList.vue'
import { useTable } from '@/composables/useTable'
import { useFilters } from '@/composables/useFilters'
import { usePagination } from '@/composables/usePagination'
import { useBagRequests } from '@/composables/useBagRequests'
import { bagRequestsService } from '@/services/bagRequestsService'
import { useAuthStore } from '@/stores/auth'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { formatDate } from '@/utils/datetime'

const { t, n, locale } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const evaluatorId = computed(() => auth.user?.id ?? null)

const { localized, statusFilterOptions } = useBagRequests()

useHead({ title: () => t('bagRequests.evaluator.title') })

const filters = useFilters({ status: '' })
const { search } = filters
const paginator = usePagination({ perPage: 10 })
const { pagination, setPage } = paginator

const { items, status, errorMessage, load } = useTable({
  fetcher: (params) => bagRequestsService.listForEvaluator(evaluatorId.value, params),
  filters,
  pagination: paginator,
})

const columns = computed(() => [
  { key: 'entityName', label: t('bagRequests.columns.entity') },
  { key: 'reference', label: t('bagRequests.columns.reference') },
  { key: 'context', label: t('bagRequests.columns.context') },
  { key: 'status', label: t('bagRequests.columns.status') },
  { key: 'assignedAt', label: t('bagRequests.columns.assignedAt') },
  { key: 'actions', label: t('components.actions.label') },
])

function dateLabel(value) {
  if (!value) return '—'
  return formatDate(value, locale.value, { dateStyle: 'medium' })
}

function rowActions(row) {
  if (row.status === 'assigned') {
    return [{ key: 'analyze', label: t('bagRequests.actions.startAnalysis'), icon: 'fa-solid fa-play' }]
  }
  if (row.status !== 'completed' && row.status !== 'cancelled') {
    return [{ key: 'open', label: t('bagRequests.actions.open'), icon: 'fa-solid fa-folder-open' }]
  }
  return [{ key: 'view', label: t('bagRequests.actions.view'), icon: 'fa-solid fa-eye' }]
}

function openManage(row) {
  router.push({ name: ROUTE_NAMES.BAG_MANAGE, query: { request: row.id } })
}
</script>

<template>
  <section class="bag-requests">
    <header class="bag-requests__header">
      <div>
        <p class="bag-requests__eyebrow">{{ t('bagRequests.evaluator.eyebrow') }}</p>
        <h1 class="bag-requests__title">{{ t('bagRequests.evaluator.title') }}</h1>
        <p class="bag-requests__subtitle">{{ t('bagRequests.evaluator.subtitle') }}</p>
      </div>
    </header>

    <ListToolbar v-model:search="search" :search-placeholder="t('bagRequests.evaluator.searchPlaceholder')">
      <template #filters>
        <DropdownList
          :model-value="filters.filters.status"
          :options="statusFilterOptions"
          :placeholder="t('bagRequests.filters.status')"
          @update:model-value="filters.setFilter('status', $event)"
        />
      </template>
    </ListToolbar>

    <DataTable
      :columns="columns"
      :items="items"
      :status="status"
      :error-message="errorMessage"
      :empty-title="t('bagRequests.evaluator.emptyTitle')"
      :empty-message="t('bagRequests.evaluator.emptyMessage')"
      @retry="load"
    >
      <template #cell-entityName="{ item }">
        <span class="bag-requests__entity">{{ localized(item.entityName) }}</span>
        <Badge v-if="item.hasPreviousBag" variant="muted">{{ t('bagRequests.repeat') }}</Badge>
      </template>
      <template #cell-reference="{ item }">
        <span class="bag-requests__reference">
          {{ t('bagRequests.referenceValue', { score: n(item.referenceSnapshot.scorePercent, 'default') }) }}
        </span>
      </template>
      <template #cell-context="{ item }">
        <span class="bag-requests__context">
          <span v-if="item.selfScorePercent != null" class="bag-requests__context-chip">
            {{ t('bagRequests.contextSelf', { score: n(item.selfScorePercent, 'default') }) }}
          </span>
          <span v-if="item.advancedScorePercent != null" class="bag-requests__context-chip">
            {{ t('bagRequests.contextAdvanced', { score: n(item.advancedScorePercent, 'default') }) }}
          </span>
          <span v-if="item.selfScorePercent == null && item.advancedScorePercent == null">—</span>
        </span>
      </template>
      <template #cell-status="{ item }">
        <StatusBadge :status="item.status" />
      </template>
      <template #cell-assignedAt="{ item }">
        <span class="bag-requests__date">{{ dateLabel(item.assignedAt) }}</span>
      </template>
      <template #cell-actions="{ item }">
        <ActionsDropdown :actions="rowActions(item)" @select="openManage(item)" />
      </template>
    </DataTable>

    <Pagination
      v-if="status === 'success' && pagination.lastPage > 1"
      :page="pagination.page"
      :last-page="pagination.lastPage"
      :disabled="status === 'loading'"
      @update:page="setPage($event)"
    />
  </section>
</template>
