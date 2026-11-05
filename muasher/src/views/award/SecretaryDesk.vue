<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import StatsCards from '@/components/data-list/StatsCards.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import AwardPageHeader from '@/components/committee/AwardPageHeader.vue'
import SecretaryRequestTable from '@/components/committee/SecretaryRequestTable.vue'
import { useSecretaryDesk } from '@/composables/useSecretaryDesk'
import {
  COMMITTEE_REQUEST_STATUSES as C,
  RESULT_STATUSES as R,
  SECRETARY_SORTS,
  SECRETARY_STATUSES,
} from '@/constants/committee'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'
import { statusLabelKey } from '@/utils/canonicalStatus'

const { t, locale } = useI18n()
const route = useRoute()
const cycleId = computed(() => Number(route.params.id))
const {
  items,
  status,
  errorMessage,
  load,
  filters,
  paginator,
  searchInput,
  cycle,
  counts,
  setFilter,
  setSort,
} = useSecretaryDesk(cycleId)
const { pagination, setPage } = paginator

useHead({ title: () => t('award.secretary.title') })

const statCards = computed(() => {
  if (!counts.value) return []
  const value = counts.value
  return [
    {
      id: 'awaiting',
      icon: 'stamp',
      value: value[C.AWAITING_SECRETARY],
      label: t('status.awaiting_secretary_approval'),
    },
    {
      id: 'committee',
      icon: 'users-two',
      value: value[C.UNDER_COMMITTEE] + value[C.RETURNED],
      label: t('award.secretary.stats.inCommittee'),
    },
    {
      id: 'discussion',
      icon: 'chat',
      value: value[C.DISCUSSION],
      label: t('status.committee_discussion'),
    },
    {
      id: 'approved',
      icon: 'check-circle',
      value: Object.values(R).reduce((sum, key) => sum + value[key], 0),
      label: t('award.secretary.stats.approved'),
    },
  ]
})
const statusOptions = computed(() => [
  { value: '', label: t('award.secretary.filters.allStatuses') },
  ...SECRETARY_STATUSES.map((value) => ({ value, label: t(statusLabelKey(value)) })),
])
const regionOptions = computed(() => [
  { value: '', label: t('award.secretary.filters.allRegions') },
  ...(cycle.value?.regions ?? []).map((region) => ({
    value: String(region.id),
    label: localizedText(region.name, locale.value),
  })),
])
const sortOptions = computed(() =>
  SECRETARY_SORTS.map((value) => ({
    value,
    label: t(`award.secretary.sorts.${value.startsWith('-') ? `${value.slice(1)}_desc` : value}`),
  })),
)
</script>

<template>
  <section class="cycles-page secretary-desk">
    <AwardPageHeader
      :title="t('award.secretary.title')"
      :cycle="cycle"
      :back="{ name: ROUTE_NAMES.AWARD_SECRETARY }"
      :back-label="t('award.common.backToCycles')"
    />
    <StatsCards :stats="statCards" :loading="!counts && status === 'loading'" />
    <Card>
      <ListToolbar
        v-model:search="searchInput"
        :search-placeholder="t('award.secretary.searchPlaceholder')"
      >
        <template #filters>
          <AppSelect
            variant="filter"
            :model-value="filters.filters.status"
            :options="statusOptions"
            :label="t('award.secretary.filters.status')"
            @update:model-value="setFilter('status', $event)"
          />
          <AppSelect
            variant="filter"
            :model-value="filters.filters.regionId"
            :options="regionOptions"
            :label="t('award.secretary.filters.region')"
            @update:model-value="setFilter('regionId', $event)"
          />
          <AppSelect
            variant="filter"
            :model-value="filters.sort.value"
            :options="sortOptions"
            :label="t('award.secretary.filters.sort')"
            @update:model-value="setSort($event)"
          />
        </template>
      </ListToolbar>
      <SecretaryRequestTable
        :cycle-id="cycleId"
        :items="items"
        :status="status"
        :error-message="errorMessage"
        @retry="load"
      />
      <Pagination
        v-if="status === 'success' && pagination.lastPage > 1"
        :page="pagination.page"
        :last-page="pagination.lastPage"
        @update:page="setPage($event)"
      />
    </Card>
  </section>
</template>
