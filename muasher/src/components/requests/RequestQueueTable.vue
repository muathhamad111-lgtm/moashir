<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Tooltip from '@/components/ui/Tooltip.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import OverdueBadge from '@/components/accredited/OverdueBadge.vue'
import RequestQueueRequestCell from './RequestQueueRequestCell.vue'
import RequestQueueStatusCell from './RequestQueueStatusCell.vue'
import RequestQueueAssessorCell from './RequestQueueAssessorCell.vue'
import LevelBadge from '@/components/level/LevelBadge.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'
import { displayTierOf } from '@/utils/levelTier'
import { assignmentModeOf } from '@/utils/assessments/reviewStages'
import { ASSIGNMENT_MODES } from '@/constants/accredited'

const props = defineProps({
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
  showsRegion: { type: Boolean, default: false },
  showsCycle: { type: Boolean, default: false },
  canAssign: { type: Boolean, default: false },
})

const emit = defineEmits(['retry', 'assign'])
const { t, locale } = useI18n()

// The viewer picks the optional columns («الأعمدة»); the request and actions always show.
const columns = computed(() => [
  { key: 'number', label: t('requests.columns.number') },
  { key: 'level', label: t('requests.columns.level') },
  ...(props.showsCycle ? [{ key: 'cycle', label: t('requests.columns.cycle') }] : []),
  ...(props.showsRegion ? [{ key: 'region', label: t('requests.columns.region') }] : []),
  { key: 'status', label: t('requests.columns.status') },
  { key: 'assessor', label: t('requests.columns.assessor') },
  { key: 'submittedAt', label: t('requests.columns.submittedAt') },
  { key: 'dueAt', label: t('requests.columns.dueAt') },
  { key: 'actions', label: t('components.actions.label') },
])

function rowModeOf(item) {
  return props.canAssign ? assignmentModeOf(item) : null
}

function detailRouteOf(item) {
  return { name: ROUTE_NAMES.REQUESTS_DETAIL, params: { id: item.id } }
}
</script>

<template>
  <DataTable
    table-key="requests-queue"
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="t('requests.index.emptyTitle')"
    :empty-message="t('requests.index.emptyMessage')"
    @retry="emit('retry')"
  >
    <template #cell-number="{ item }">
      <RequestQueueRequestCell :item="item" :to="detailRouteOf(item)" />
    </template>
    <template #cell-level="{ item }">
      <LevelBadge
        v-if="item.entity"
        :tier="displayTierOf(item.entity)"
        size="s"
        :certificate="item.entity.lastLevel"
      />
    </template>
    <template #cell-cycle="{ item }"
      >{{ item.awardCycle ? localizedText(item.awardCycle.name, locale) : '' }}
      <bdi v-if="item.awardCycle?.year" class="u-ltr">{{ item.awardCycle.year }}</bdi></template
    >
    <template #cell-region="{ item }">{{
      item.region ? localizedText(item.region.name, locale) : ''
    }}</template>
    <template #cell-status="{ item }"><RequestQueueStatusCell :item="item" /></template>
    <template #cell-assessor="{ item }"><RequestQueueAssessorCell :item="item" /></template>
    <template #cell-submittedAt="{ item }"
      ><DateStamp :value="item.submittedAt" :with-time="false"
    /></template>
    <template #cell-dueAt="{ item }">
      <span class="request-queue__due">
        <DateStamp :value="item.dueAt" :with-time="false" />
        <OverdueBadge v-if="item.dueAt" :overdue="item.isOverdue" />
      </span>
    </template>
    <template #cell-actions="{ item }">
      <span class="request-queue__actions">
        <BaseButton
          v-if="rowModeOf(item)"
          :variant="rowModeOf(item) === ASSIGNMENT_MODES.ASSIGN ? 'primary' : 'secondary'"
          size="sm"
          :icon="rowModeOf(item) === ASSIGNMENT_MODES.ASSIGN ? 'user-check' : 'pen'"
          :aria-label="t(`requests.rowActions.${rowModeOf(item)}For`, { number: item.number })"
          data-testid="queue-row-assign"
          @click="emit('assign', { item, mode: rowModeOf(item) })"
          >{{ t(`requests.rowActions.${rowModeOf(item)}`) }}</BaseButton
        >
        <Tooltip :text="t('requests.rowActions.view')">
          <template #default="{ describedBy }">
            <BaseButton
              variant="secondary"
              size="sm"
              icon="eye"
              icon-only
              :to="detailRouteOf(item)"
              :aria-label="t('requests.rowActions.viewFor', { number: item.number })"
              :aria-describedby="describedBy"
            />
          </template>
        </Tooltip>
      </span>
    </template>
  </DataTable>
</template>
