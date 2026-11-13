<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import AppMenu from '@/components/ui/AppMenu.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import FrozenBadge from '@/components/entities/FrozenBadge.vue'
import LevelBadge from '@/components/level/LevelBadge.vue'
import { displayTierOf, reassessmentOf } from '@/utils/levelTier'
import { useEntityActions } from '@/composables/useEntityActions'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { ROUTE_NAMES } from '@/constants/routeNames'

const props = defineProps({
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
  full: { type: Boolean, default: true },
  deleted: { type: Boolean, default: false },
  emptyTitle: { type: String, required: true },
  emptyMessage: { type: String, default: '' },
})

const emit = defineEmits(['retry', 'action'])

const { t } = useI18n()
const { rowActionsFor } = useEntityActions()
const { localized, enumLabel, date, dateTime } = useEntityDisplay()

const COLUMN_SETS = {
  full: ['reference', 'name', 'level', 'entityType', 'region', 'status', 'createdAt', 'actions'],
  contact: [
    'reference',
    'name',
    'level',
    'authorizedPersonName',
    'officialEmail',
    'status',
    'actions',
  ],
  deleted: ['reference', 'name', 'region', 'deletedAt', 'status', 'actions'],
}

function tierOf(item) {
  return displayTierOf(item, item.hasOpenAccreditedRequest === true)
}

const columns = computed(() => {
  const set = props.deleted ? 'deleted' : props.full ? 'full' : 'contact'
  return COLUMN_SETS[set].map((key) => ({ key, label: t(`entities.columns.${key}`) }))
})
</script>

<template>
  <DataTable
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="emptyTitle"
    :empty-message="emptyMessage"
    @retry="emit('retry')"
  >
    <template #cell-reference="{ item }">
      <span class="entity-table__reference u-ltr">{{ item.reference }}</span>
    </template>
    <template #cell-name="{ item }">
      <router-link
        class="entity-table__name"
        :to="{ name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: item.id } }"
      >
        {{ localized(item.name) }}
      </router-link>
    </template>
    <template #cell-level="{ item }">
      <LevelBadge
        :tier="tierOf(item)"
        size="s"
        :reassessment="reassessmentOf(tierOf(item), item.hasOpenAccreditedRequest)"
        :certificate="item.lastLevel"
      />
    </template>
    <template #cell-entityType="{ item }">{{ enumLabel('entityType', item.entityType) }}</template>
    <template #cell-region="{ item }">{{ localized(item.region?.name) }}</template>
    <template #cell-officialEmail="{ item }">
      <span class="u-ltr">{{ item.officialEmail }}</span>
    </template>
    <template #cell-createdAt="{ item }">{{ date(item.createdAt) }}</template>
    <template #cell-deletedAt="{ item }">{{ dateTime(item.deletedAt) }}</template>
    <template #cell-status="{ item }">
      <span class="entity-table__status">
        <StatusBadge :status="item.status" />
        <FrozenBadge v-if="item.isFrozen" />
      </span>
    </template>
    <template #cell-actions="{ item }">
      <AppMenu
        :actions="rowActionsFor(item)"
        :label="t('entities.actions.menu', { name: localized(item.name) })"
        @select="emit('action', $event, item)"
      />
    </template>
  </DataTable>
</template>
