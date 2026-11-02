<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import StatsCards from '@/components/data-list/StatsCards.vue'
import ActionsDropdown from '@/components/data-list/ActionsDropdown.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Badge from '@/components/ui/Badge.vue'
import DropdownList from '@/components/ui/DropdownList.vue'
import AssignEvaluatorModal from '@/components/bag/AssignEvaluatorModal.vue'
import { useTable } from '@/composables/useTable'
import { useFilters } from '@/composables/useFilters'
import { usePagination } from '@/composables/usePagination'
import { useToast } from '@/composables/useToast'
import { useBagRequests } from '@/composables/useBagRequests'
import { bagRequestsService } from '@/services/bagRequestsService'
import { formatDate } from '@/utils/datetime'

const { t, n, locale } = useI18n()
const toast = useToast()

const { localized, statusFilterOptions, evaluatorOptions, evaluatorsLoading, loadEvaluators } =
  useBagRequests()

useHead({ title: () => t('bagRequests.index.title') })

const filters = useFilters({ status: '' })
const { search } = filters
const paginator = usePagination({ perPage: 10 })
const { pagination, setPage } = paginator
const stats = ref(null)

const { items, status, errorMessage, load } = useTable({
  fetcher: async (params) => {
    const envelope = await bagRequestsService.list(params)
    stats.value = envelope.meta?.stats ?? null
    return envelope
  },
  filters,
  pagination: paginator,
})

const statCards = computed(() => [
  { id: 'total', icon: 'fa-solid fa-inbox', value: stats.value?.total ?? 0, label: t('bagRequests.stats.total') },
  { id: 'awaiting', icon: 'fa-solid fa-user-clock', value: stats.value?.awaiting ?? 0, label: t('bagRequests.stats.awaiting') },
  { id: 'active', icon: 'fa-solid fa-briefcase', value: stats.value?.byStatus?.active ?? 0, label: t('bagRequests.stats.active') },
  { id: 'completed', icon: 'fa-solid fa-flag-checkered', value: stats.value?.byStatus?.completed ?? 0, label: t('bagRequests.stats.completed') },
])

const columns = computed(() => [
  { key: 'entityName', label: t('bagRequests.columns.entity') },
  { key: 'reference', label: t('bagRequests.columns.reference') },
  { key: 'paidAt', label: t('bagRequests.columns.paidAt') },
  { key: 'status', label: t('bagRequests.columns.status') },
  { key: 'evaluator', label: t('bagRequests.columns.evaluator') },
  { key: 'actions', label: t('components.actions.label') },
])

function dateLabel(value) {
  if (!value) return '—'
  return formatDate(value, locale.value, { dateStyle: 'medium' })
}

const CLOSED = ['completed', 'cancelled']

function rowActions(row) {
  const actions = []
  if (row.status === 'awaiting-assignment') {
    actions.push({ key: 'assign', label: t('bagRequests.actions.assign'), icon: 'fa-solid fa-user-plus' })
  } else if (!CLOSED.includes(row.status)) {
    actions.push({ key: 'reassign', label: t('bagRequests.actions.reassign'), icon: 'fa-solid fa-user-pen' })
  }
  return actions
}

const modalOpen = ref(false)
const modalMode = ref('assign')
const modalBusy = ref(false)
const activeRow = ref(null)

async function openAssign(row, mode) {
  activeRow.value = row
  modalMode.value = mode
  modalOpen.value = true
  await loadEvaluators()
}

async function onConfirm({ evaluatorId, reason }) {
  if (!activeRow.value) return
  modalBusy.value = true
  try {
    if (modalMode.value === 'reassign') {
      await bagRequestsService.reassign(activeRow.value.id, { evaluatorId, reason })
      toast.success(t('bagRequests.toasts.reassigned'))
    } else {
      await bagRequestsService.assign(activeRow.value.id, { evaluatorId })
      toast.success(t('bagRequests.toasts.assigned'))
    }
    modalOpen.value = false
    await load()
  } catch {
    toast.error(t('bagRequests.toasts.assignFailed'))
  } finally {
    modalBusy.value = false
  }
}

function onAction(key, row) {
  if (key === 'assign') openAssign(row, 'assign')
  else if (key === 'reassign') openAssign(row, 'reassign')
}
</script>

<template>
  <section class="bag-requests">
    <header class="bag-requests__header">
      <div>
        <p class="bag-requests__eyebrow">{{ t('bagRequests.index.eyebrow') }}</p>
        <h1 class="bag-requests__title">{{ t('bagRequests.index.title') }}</h1>
        <p class="bag-requests__subtitle">{{ t('bagRequests.index.subtitle') }}</p>
      </div>
    </header>

    <StatsCards :stats="statCards" :loading="status === 'loading' && !stats" />

    <ListToolbar v-model:search="search" :search-placeholder="t('bagRequests.index.searchPlaceholder')">
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
      :empty-title="t('bagRequests.index.emptyTitle')"
      :empty-message="t('bagRequests.index.emptyMessage')"
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
      <template #cell-paidAt="{ item }">
        <span class="bag-requests__date">{{ dateLabel(item.paidAt) }}</span>
      </template>
      <template #cell-status="{ item }">
        <StatusBadge :status="item.status" />
      </template>
      <template #cell-evaluator="{ item }">
        <span v-if="item.assignedEvaluatorName" class="bag-requests__evaluator">
          <i class="fa-solid fa-user-check bag-requests__evaluator-icon" aria-hidden="true"></i>
          {{ localized(item.assignedEvaluatorName) }}
        </span>
        <span v-else class="bag-requests__unassigned">{{ t('bagRequests.unassigned') }}</span>
      </template>
      <template #cell-actions="{ item }">
        <ActionsDropdown v-if="rowActions(item).length" :actions="rowActions(item)" @select="onAction($event, item)" />
        <span v-else class="bag-requests__no-actions">—</span>
      </template>
    </DataTable>

    <Pagination
      v-if="status === 'success' && pagination.lastPage > 1"
      :page="pagination.page"
      :last-page="pagination.lastPage"
      :disabled="status === 'loading'"
      @update:page="setPage($event)"
    />

    <AssignEvaluatorModal
      v-model="modalOpen"
      :mode="modalMode"
      :entity-name="activeRow ? localized(activeRow.entityName) : ''"
      :options="evaluatorOptions"
      :loading="evaluatorsLoading"
      :busy="modalBusy"
      :current-evaluator-id="activeRow?.assignedEvaluatorId ?? null"
      @confirm="onConfirm"
    />
  </section>
</template>
