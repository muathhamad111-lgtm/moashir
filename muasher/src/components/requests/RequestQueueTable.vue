<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import DataTable from '@/components/data-list/DataTable.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import OverdueBadge from '@/components/accredited/OverdueBadge.vue'
import PathBadge from '@/components/ui/PathBadge.vue'
import LevelBadge from '@/components/level/LevelBadge.vue'
import { tierOfLevel } from '@/utils/levelTier'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
  showsRegion: { type: Boolean, default: false },
})

const emit = defineEmits(['retry'])
const { t, locale } = useI18n()

const columns = computed(() => [
  { key: 'number', label: t('requests.columns.number') },
  { key: 'entity', label: t('requests.columns.entity') },
  { key: 'path', label: t('requests.columns.pathAndCycle') },
  ...(props.showsRegion ? [{ key: 'region', label: t('requests.columns.region') }] : []),
  { key: 'status', label: t('requests.columns.status') },
  { key: 'assessor', label: t('requests.columns.assessor') },
  { key: 'submittedAt', label: t('requests.columns.submittedAt') },
  { key: 'dueAt', label: t('requests.columns.dueAt') },
])
</script>

<template>
  <DataTable
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="t('requests.index.emptyTitle')"
    :empty-message="t('requests.index.emptyMessage')"
    @retry="emit('retry')"
  >
    <template #cell-number="{ item }">
      <RouterLink
        class="request-queue__link"
        :to="{ name: ROUTE_NAMES.REQUESTS_DETAIL, params: { id: item.id } }"
      >
        <bdi dir="ltr">{{ item.number }}</bdi>
      </RouterLink>
    </template>
    <template #cell-entity="{ item }">
      <span class="request-queue__entity">
        <span>{{ localizedText(item.entity?.name, locale) }}</span>
        <LevelBadge v-if="item.entity" :tier="tierOfLevel(item.entity.level)" size="s" />
      </span>
    </template>
    <template #cell-path="{ item }">
      <span class="request-queue__path">
        <PathBadge v-if="item.path" :path="item.path" :label="item.pathLabel" compact />
        <span v-if="item.awardCycle" class="request-queue__muted">{{
          localizedText(item.awardCycle.name, locale)
        }}</span>
      </span>
    </template>
    <template #cell-region="{ item }">{{
      item.region ? localizedText(item.region.name, locale) : ''
    }}</template>
    <template #cell-status="{ item }"><StatusBadge :status="item.status" /></template>
    <template #cell-assessor="{ item }">
      <span :class="{ 'request-queue__muted': !item.assessor }">{{
        item.assessor?.name ?? t('requests.unassigned')
      }}</span>
    </template>
    <template #cell-submittedAt="{ item }"
      ><DateStamp :value="item.submittedAt" :with-time="false"
    /></template>
    <template #cell-dueAt="{ item }">
      <span class="request-queue__due">
        <DateStamp :value="item.dueAt" :with-time="false" />
        <OverdueBadge v-if="item.dueAt" :overdue="item.isOverdue" />
      </span>
    </template>
  </DataTable>
</template>
