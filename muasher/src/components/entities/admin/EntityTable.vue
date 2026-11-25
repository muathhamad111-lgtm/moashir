<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import RowActions from '@/components/data-list/RowActions.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import FrozenBadge from '@/components/entities/FrozenBadge.vue'
import LevelBadge from '@/components/level/LevelBadge.vue'
import EntityLastAssessment from './EntityLastAssessment.vue'
import { displayTierOf, reassessmentOf } from '@/utils/levelTier'
import { entityInlineActionsFor, useEntityActions } from '@/composables/useEntityActions'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { ENTITY_COLUMN_SETS, ENTITY_FACT_FIELDS, entityColumnKindOf } from './entityTableColumns'

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
const { localized, enumLabel, date, dateTime, valueOf, isLtr } = useEntityDisplay()

function tierOf(item) {
  return displayTierOf(item, item.hasOpenAccreditedRequest === true)
}

const columnSet = computed(() => (props.deleted ? 'deleted' : props.full ? 'full' : 'contact'))
const columns = computed(() =>
  ENTITY_COLUMN_SETS[columnSet.value].map((key) => ({
    key,
    label: t(`entities.columns.${key}`),
    kind: entityColumnKindOf(key),
  })),
)
</script>

<template>
  <DataTable
    :table-key="`entities-${columnSet}`"
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="emptyTitle"
    :empty-message="emptyMessage"
    @retry="emit('retry')"
  >
    <template #cell-name="{ item }">
      <span class="entity-table__entity">
        <router-link
          class="entity-table__name"
          :to="{ name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: item.id } }"
        >
          {{ localized(item.name) }}
        </router-link>
        <span class="entity-table__reference u-ltr">{{ item.reference }}</span>
      </span>
    </template>
    <template #cell-level="{ item }">
      <span class="entity-table__level">
        <LevelBadge
          :tier="tierOf(item)"
          size="s"
          :reassessment="reassessmentOf(tierOf(item), item.hasOpenAccreditedRequest)"
          :certificate="item.lastLevel"
        />
      </span>
    </template>
    <template #cell-lastAssessment="{ item }">
      <EntityLastAssessment :assessment="item.lastAccreditedAssessment" />
    </template>
    <template #cell-typeRegion="{ item }">
      <span class="entity-table__stack">
        <span>{{ enumLabel('entityType', item.entityType) }}</span>
        <span class="entity-table__muted">{{ localized(item.region?.name) }}</span>
      </span>
    </template>
    <template #cell-statusRegistered="{ item }">
      <span class="entity-table__stack">
        <span class="entity-table__status">
          <StatusBadge :status="item.status" />
          <FrozenBadge v-if="item.isFrozen" />
        </span>
        <span class="entity-table__muted">{{
          t('entities.registeredOn', { date: date(item.createdAt) })
        }}</span>
      </span>
    </template>
    <template #cell-region="{ item }">{{ localized(item.region?.name) }}</template>
    <template #cell-officialEmail="{ item }">
      <span class="u-ltr">{{ item.officialEmail }}</span>
    </template>
    <template #cell-deletedAt="{ item }">{{ dateTime(item.deletedAt) }}</template>
    <template #cell-status="{ item }">
      <span class="entity-table__status">
        <StatusBadge :status="item.status" />
        <FrozenBadge v-if="item.isFrozen" />
      </span>
    </template>
    <template v-for="field in ENTITY_FACT_FIELDS" :key="field" #[`cell-${field}`]="{ item }">
      <span :class="{ 'u-ltr': isLtr(field) }">{{ valueOf(item, field) }}</span>
    </template>
    <template #cell-actions="{ item }">
      <RowActions
        :actions="rowActionsFor(item)"
        :name="localized(item.name)"
        :inline-keys="entityInlineActionsFor(item)"
        @select="emit('action', $event, item)"
      />
    </template>
  </DataTable>
</template>
