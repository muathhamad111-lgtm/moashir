<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import StatsCards from '@/components/data-list/StatsCards.vue'
import ActionsDropdown from '@/components/data-list/ActionsDropdown.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Badge from '@/components/ui/Badge.vue'
import DropdownList from '@/components/ui/DropdownList.vue'
import KpiForm from '@/components/kpis/KpiForm.vue'
import { useTable } from '@/composables/useTable'
import { useFilters } from '@/composables/useFilters'
import { usePagination } from '@/composables/usePagination'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { useKpisAdmin } from '@/composables/useKpisAdmin'
import { kpisService } from '@/services/kpisService'

const { t, n } = useI18n()
const { confirm } = useConfirm()
const toast = useToast()
const {
  localized,
  publishedFilterOptions,
  colorLabel,
  categoryLabel,
  statusVariant,
} = useKpisAdmin()

const filters = useFilters({ published: '' })
const { search } = filters
const paginator = usePagination({ perPage: 12 })
const { pagination, setPage } = paginator
const stats = ref(null)
const reorderingId = ref(null)

const { items, status, errorMessage, load } = useTable({
  fetcher: async (params) => {
    const envelope = await kpisService.adminList(params)
    stats.value = envelope.meta?.stats ?? null
    return envelope
  },
  filters,
  pagination: paginator,
})

const statCards = computed(() => [
  {
    id: 'total',
    icon: 'fa-solid fa-chart-column',
    value: stats.value?.total ?? 0,
    label: t('kpis.admin.stats.total'),
  },
  {
    id: 'published',
    icon: 'fa-solid fa-eye',
    value: stats.value?.published ?? 0,
    label: t('kpis.admin.stats.published'),
  },
  {
    id: 'unpublished',
    icon: 'fa-solid fa-eye-slash',
    value: stats.value?.unpublished ?? 0,
    label: t('kpis.admin.stats.unpublished'),
  },
])

const columns = computed(() => [
  { key: 'order', label: t('kpis.admin.columns.order') },
  { key: 'name', label: t('kpis.admin.columns.name') },
  { key: 'category', label: t('kpis.admin.columns.category') },
  { key: 'value', label: t('kpis.admin.columns.value') },
  { key: 'target', label: t('kpis.admin.columns.target') },
  { key: 'status', label: t('kpis.admin.columns.status') },
  { key: 'published', label: t('kpis.admin.columns.published') },
  { key: 'actions', label: t('components.actions.label') },
])

function statusLabel(key) {
  return key ? t(`kpis.statuses.${key}`) : ''
}

const isFiltered = computed(
  () => Boolean(search.value) || Boolean(filters.filters.published),
)

const canReorder = computed(() => !isFiltered.value)

function fmt(value) {
  return typeof value === 'number' ? n(value, 'default') : value
}

function rowActions(row) {
  return [
    { key: 'edit', label: t('kpis.admin.actions.edit'), icon: 'fa-solid fa-pen' },
    row.published
      ? {
          key: 'unpublish',
          label: t('kpis.admin.actions.unpublish'),
          icon: 'fa-solid fa-eye-slash',
        }
      : { key: 'publish', label: t('kpis.admin.actions.publish'), icon: 'fa-solid fa-eye' },
    {
      key: 'delete',
      label: t('kpis.admin.actions.delete'),
      icon: 'fa-solid fa-trash',
      variant: 'danger',
    },
  ]
}

const formOpen = ref(false)
const editing = ref(null)

function openCreate() {
  editing.value = null
  formOpen.value = true
}

function openEdit(row) {
  editing.value = row
  formOpen.value = true
}

async function onSaved({ id, payload }) {
  try {
    if (id) {
      await kpisService.update(id, payload)
      toast.success(t('kpis.admin.toasts.updated'))
    } else {
      await kpisService.create(payload)
      toast.success(t('kpis.admin.toasts.created'))
    }
    formOpen.value = false
    await load()
  } catch {
    toast.error(t('kpis.admin.toasts.saveFailed'))
  }
}

async function togglePublished(row) {
  const publishing = !row.published
  const accepted = await confirm({
    title: publishing
      ? t('kpis.admin.confirm.publishTitle')
      : t('kpis.admin.confirm.unpublishTitle'),
    message: publishing
      ? t('kpis.admin.confirm.publishMessage', { name: localized(row.name) })
      : t('kpis.admin.confirm.unpublishMessage', { name: localized(row.name) }),
    confirmLabel: publishing ? t('kpis.admin.actions.publish') : t('kpis.admin.actions.unpublish'),
    variant: publishing ? 'primary' : 'danger',
  })
  if (!accepted) return
  try {
    await kpisService.setPublished(row.id, publishing)
    toast.success(publishing ? t('kpis.admin.toasts.published') : t('kpis.admin.toasts.unpublished'))
    await load()
  } catch {
    toast.error(t('kpis.admin.toasts.publishFailed'))
  }
}

async function deleteKpi(row) {
  const accepted = await confirm({
    title: t('kpis.admin.confirm.deleteTitle'),
    message: t('kpis.admin.confirm.deleteMessage', { name: localized(row.name) }),
    confirmLabel: t('kpis.admin.actions.delete'),
    variant: 'danger',
  })
  if (!accepted) return
  try {
    await kpisService.remove(row.id)
    toast.success(t('kpis.admin.toasts.deleted'))
    await load()
  } catch {
    toast.error(t('kpis.admin.toasts.deleteFailed'))
  }
}

async function move(row, direction) {
  if (reorderingId.value) return
  reorderingId.value = row.id
  try {
    await kpisService.reorder(row.id, direction)
    await load()
  } catch {
    toast.error(t('kpis.admin.toasts.reorderFailed'))
  } finally {
    reorderingId.value = null
  }
}

function isFirst(row) {
  return row.order <= 1
}

function isLast(row) {
  return row.order >= (stats.value?.total ?? row.order)
}

function onAction(key, row) {
  if (key === 'edit') openEdit(row)
  else if (key === 'publish' || key === 'unpublish') togglePublished(row)
  else if (key === 'delete') deleteKpi(row)
}
</script>

<template>
  <section class="kpis-admin">
    <header class="kpis-admin__header">
      <div>
        <h1 class="kpis-admin__title">{{ t('kpis.admin.index.title') }}</h1>
        <p class="kpis-admin__subtitle">{{ t('kpis.admin.index.subtitle') }}</p>
      </div>
      <RouterLink class="kpis-admin__public-link" to="/kpis" target="_blank" rel="noopener">
        <i class="fa-solid fa-arrow-up-right-from-square u-flip-in-rtl" aria-hidden="true"></i>
        {{ t('kpis.admin.index.viewPublic') }}
      </RouterLink>
    </header>

    <StatsCards :stats="statCards" :loading="status === 'loading' && !stats" />

    <ListToolbar
      v-model:search="search"
      :search-placeholder="t('kpis.admin.index.searchPlaceholder')"
    >
      <template #filters>
        <DropdownList
          :model-value="filters.filters.published"
          :options="publishedFilterOptions"
          :placeholder="t('kpis.admin.filters.published')"
          @update:model-value="filters.setFilter('published', $event)"
        />
      </template>
      <template #actions>
        <BaseButton variant="primary" size="sm" icon="fa-solid fa-plus" @click="openCreate">
          {{ t('kpis.admin.index.add') }}
        </BaseButton>
      </template>
    </ListToolbar>

    <p v-if="!canReorder && status === 'success'" class="kpis-admin__reorder-note" role="note">
      <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
      {{ t('kpis.admin.index.reorderNote') }}
    </p>

    <DataTable
      :columns="columns"
      :items="items"
      :status="status"
      :error-message="errorMessage"
      :empty-title="t('kpis.admin.index.emptyTitle')"
      :empty-message="t('kpis.admin.index.emptyMessage')"
      @retry="load"
    >
      <template #empty-action>
        <BaseButton variant="primary" icon="fa-solid fa-plus" @click="openCreate">
          {{ t('kpis.admin.index.add') }}
        </BaseButton>
      </template>

      <template #cell-order="{ item }">
        <div class="kpis-admin__order">
          <span class="kpis-admin__order-number" aria-hidden="true">{{ fmt(item.order) }}</span>
          <div class="kpis-admin__reorder">
            <button
              type="button"
              class="kpis-admin__reorder-btn"
              :disabled="!canReorder || isFirst(item) || Boolean(reorderingId)"
              :aria-label="t('kpis.admin.actions.moveUp', { name: localized(item.name) })"
              @click="move(item, 'up')"
            >
              <i class="fa-solid fa-chevron-up" aria-hidden="true"></i>
            </button>
            <button
              type="button"
              class="kpis-admin__reorder-btn"
              :disabled="!canReorder || isLast(item) || Boolean(reorderingId)"
              :aria-label="t('kpis.admin.actions.moveDown', { name: localized(item.name) })"
              @click="move(item, 'down')"
            >
              <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
          </div>
        </div>
      </template>

      <template #cell-name="{ item }">
        <div class="kpis-admin__name">
          <span class="kpis-admin__name-icon" :class="`kpis-admin__name-icon--${item.color}`" aria-hidden="true">
            <i :class="item.icon"></i>
          </span>
          <div class="kpis-admin__name-text">
            <span class="kpis-admin__name-label">{{ localized(item.name) }}</span>
            <span class="kpis-admin__name-color">{{ colorLabel(item.color) }}</span>
          </div>
        </div>
      </template>

      <template #cell-category="{ item }">
        <span class="kpis-admin__category">{{ categoryLabel(item.category) || '—' }}</span>
      </template>

      <template #cell-value="{ item }">
        <span class="kpis-admin__number">{{ fmt(item.value) }}</span>
      </template>

      <template #cell-target="{ item }">
        <span class="kpis-admin__number">{{ item.target === null || item.target === undefined ? '—' : fmt(item.target) }}</span>
      </template>

      <template #cell-status="{ item }">
        <Badge v-if="item.status" :variant="statusVariant(item.status)">
          {{ statusLabel(item.status) }}
        </Badge>
        <span v-else class="kpis-admin__unit">—</span>
      </template>

      <template #cell-published="{ item }">
        <StatusBadge :status="item.published ? 'published' : 'unpublished'" />
      </template>

      <template #cell-actions="{ item }">
        <ActionsDropdown :actions="rowActions(item)" @select="onAction($event, item)" />
      </template>
    </DataTable>

    <Pagination
      v-if="status === 'success' && pagination.lastPage > 1"
      :page="pagination.page"
      :last-page="pagination.lastPage"
      :disabled="status === 'loading'"
      @update:page="setPage($event)"
    />

    <KpiForm v-model="formOpen" :kpi="editing" @saved="onSaved" />
  </section>
</template>
