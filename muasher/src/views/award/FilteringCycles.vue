<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import CycleStatusBadge from '@/components/award/CycleStatusBadge.vue'
import { useFilteringCycles } from '@/composables/useFilteringCycles'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'

const { t, locale } = useI18n()
const { status, errorMessage, cycles, load } = useFilteringCycles()

useHead({ title: () => t('filtering.cycles.title') })

const columns = computed(() => [
  { key: 'name', label: t('cycles.columns.name') },
  { key: 'status', label: t('cycles.columns.status') },
  { key: 'submissionEnd', label: t('filtering.cycles.submissionEnd') },
  { key: 'open', label: t('filtering.cycles.queue') },
])
</script>

<template>
  <section class="cycles-page">
    <header class="cycles-page__header">
      <div>
        <p class="cycles-page__eyebrow">{{ t('advanced.eyebrow') }}</p>
        <h1 class="cycles-page__title">{{ t('filtering.cycles.title') }}</h1>
        <p class="cycles-page__subtitle">{{ t('filtering.cycles.subtitle') }}</p>
      </div>
    </header>
    <Card>
      <DataTable
        :columns="columns"
        :items="cycles"
        :status="status"
        :error-message="errorMessage"
        :empty-title="t('filtering.cycles.emptyTitle')"
        :empty-message="t('filtering.cycles.emptyMessage')"
        @retry="load"
      >
        <template #cell-name="{ item }">{{ localizedText(item.name, locale) }} <bdi class="u-ltr">{{ item.year }}</bdi></template>
        <template #cell-status="{ item }"><CycleStatusBadge :status="item.status" /></template>
        <template #cell-submissionEnd="{ item }"><DateStamp :value="item.submissionEnd" :with-time="false" /></template>
        <template #cell-open="{ item }">
          <RouterLink class="cycle-table__link" :to="{ name: ROUTE_NAMES.AWARD_FILTERING_QUEUE, params: { id: item.id } }">
            {{ t('filtering.cycles.openQueue') }}
          </RouterLink>
        </template>
      </DataTable>
    </Card>
  </section>
</template>
