<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import ActionsDropdown from '@/components/data-list/ActionsDropdown.vue'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { KPI_REORDER, KPI_STATUS_VARIANTS } from '@/constants/kpis'
import { formatKpiValue } from '@/utils/kpiNumber'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
  categories: { type: Array, default: () => [] },
  localized: { type: Function, required: true },
  canReorder: { type: Boolean, default: false },
  total: { type: Number, default: 0 },
  pendingId: { type: [Number, null], default: null },
})

const emit = defineEmits(['retry', 'action', 'move', 'create'])
const { t, n } = useI18n()

const columns = computed(() =>
  ['order', 'name', 'category', 'value', 'target', 'status', 'published', 'actions'].map((key) => ({
    key,
    label: key === 'actions' ? t('components.actions.label') : t(`kpis.admin.columns.${key}`),
  })),
)

function categoryName(id) {
  return props.localized(props.categories.find((category) => category.id === id)?.name) || '—'
}

function actionsOf(kpi) {
  return [
    { key: 'edit', label: t('kpis.admin.actions.edit'), icon: 'pen' },
    { key: 'values', label: t('kpis.admin.actions.values'), icon: 'refresh-dots' },
    kpi.published
      ? { key: 'publish', label: t('kpis.admin.actions.unpublish'), icon: 'eye-slash' }
      : { key: 'publish', label: t('kpis.admin.actions.publish'), icon: 'eye' },
    kpi.showOnHomepage
      ? { key: 'feature', label: t('kpis.admin.actions.unfeature'), icon: 'star' }
      : { key: 'feature', label: t('kpis.admin.actions.feature'), icon: 'star' },
    {
      key: 'archive',
      label: t('kpis.admin.actions.archive'),
      icon: 'docs',
      variant: 'danger',
    },
  ]
}

function moveDisabled(kpi, direction) {
  if (!props.canReorder || props.pendingId !== null) return true
  return direction === KPI_REORDER.UP ? kpi.order <= 1 : kpi.order >= props.total
}
</script>

<template>
  <DataTable
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="t('kpis.admin.index.emptyTitle')"
    :empty-message="t('kpis.admin.index.emptyMessage')"
    @retry="emit('retry')"
  >
    <template #empty-action>
      <BaseButton variant="primary" icon="plus" @click="emit('create')">{{
        t('kpis.admin.index.add')
      }}</BaseButton>
    </template>
    <template #cell-order="{ item }">
      <div class="kpis-admin__order">
        <span class="kpis-admin__order-number">{{ n(item.order, 'id') }}</span>
        <div class="kpis-admin__reorder">
          <button
            v-for="direction in [KPI_REORDER.UP, KPI_REORDER.DOWN]"
            :key="direction"
            type="button"
            class="kpis-admin__reorder-btn"
            :disabled="moveDisabled(item, direction)"
            :aria-label="
              t(
                direction === KPI_REORDER.UP
                  ? 'kpis.admin.actions.moveUp'
                  : 'kpis.admin.actions.moveDown',
                { name: localized(item.name) },
              )
            "
            @click="emit('move', item, direction)"
          >
            <AppIcon :name="direction === KPI_REORDER.UP ? 'chevron-up' : 'chevron-down'" />
          </button>
        </div>
      </div>
    </template>
    <template #cell-name="{ item }">
      <div class="kpis-admin__name">
        <span
          class="kpis-admin__name-icon"
          :class="`kpis-admin__name-icon--${item.cardColorToken || 'primary'}`"
          aria-hidden="true"
        >
          <AppIcon :name="item.icon || 'chart-bar'" />
        </span>
        <div class="kpis-admin__name-text">
          <span class="kpis-admin__name-label">{{ localized(item.name) }}</span>
          <Badge v-if="item.showOnHomepage" variant="info" class="kpis-admin__featured">
            <AppIcon name="star" />
            {{ t('kpis.admin.featuredBadge') }}
          </Badge>
        </div>
      </div>
    </template>
    <template #cell-category="{ item }"
      ><span class="kpis-admin__category">{{ categoryName(item.kpiCategoryId) }}</span></template
    >
    <template #cell-value="{ item }"
      ><span class="kpis-admin__number">{{ formatKpiValue(n, item.value) || '—' }}</span></template
    >
    <template #cell-target="{ item }"
      ><span class="kpis-admin__number">{{ formatKpiValue(n, item.target) || '—' }}</span></template
    >
    <template #cell-status="{ item }">
      <Badge v-if="item.status" :variant="KPI_STATUS_VARIANTS[item.status] ?? 'muted'">{{
        t(`kpis.statuses.${item.status}`)
      }}</Badge>
      <span v-else class="kpis-admin__unit">—</span>
    </template>
    <template #cell-published="{ item }"
      ><StatusBadge :status="item.published ? 'published' : 'unpublished'"
    /></template>
    <template #cell-actions="{ item }">
      <ActionsDropdown
        :actions="actionsOf(item)"
        :label="t('kpis.admin.actions.menu', { name: localized(item.name) })"
        @select="emit('action', $event, item)"
      />
    </template>
  </DataTable>
</template>
