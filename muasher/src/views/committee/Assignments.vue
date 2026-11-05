<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import DropdownList from '@/components/ui/DropdownList.vue'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import CommitteeAssignmentTable from '@/components/committee/CommitteeAssignmentTable.vue'
import { useCommitteeAssignments } from '@/composables/useCommitteeAssignments'
import { ASSIGNMENT_FILTERS } from '@/constants/committee'
import { localizedText } from '@/utils/localizedText'
import { toCanonicalStatus } from '@/utils/canonicalStatus'

const { t, locale } = useI18n()
const { items, status, errorMessage, load, filters, paginator, setFilter, appointments } =
  useCommitteeAssignments()
const { pagination, setPage } = paginator

useHead({ title: () => t('award.work.title') })

const cycleOptions = computed(() => [
  { value: '', label: t('award.work.filters.allCycles') },
  ...appointments.value.map((appointment) => ({
    value: String(appointment.awardCycleId),
    label: localizedText(appointment.cycleName, locale.value),
  })),
])
const statusOptions = computed(() => [
  { value: '', label: t('award.work.filters.allStatuses') },
  ...ASSIGNMENT_FILTERS.map((value) => ({
    value,
    label: t(`award.states.row.${toCanonicalStatus(value)}`),
  })),
])
const overdue = computed(() => items.value.filter((row) => row.isOverdue).length)
</script>

<template>
  <section class="cycles-page committee-work">
    <header class="cycles-page__header">
      <div>
        <p class="cycles-page__eyebrow">{{ t('award.work.eyebrow') }}</p>
        <h1 class="cycles-page__title">{{ t('award.work.title') }}</h1>
        <p class="cycles-page__subtitle">{{ t('award.work.subtitle') }}</p>
      </div>
    </header>
    <AssessmentNotice
      v-if="overdue"
      variant="warning"
      :title="t('award.work.overdueTitle', { count: overdue }, overdue)"
      :message="t('award.work.overdueMessage')"
    />
    <Card>
      <div class="toolbar">
        <div class="toolbar__filters">
          <DropdownList
            :model-value="filters.filters.cycleId"
            :options="cycleOptions"
            :placeholder="t('award.work.filters.cycle')"
            @update:model-value="setFilter('cycleId', $event)"
          />
          <DropdownList
            :model-value="filters.filters.status"
            :options="statusOptions"
            :placeholder="t('award.work.filters.status')"
            @update:model-value="setFilter('status', $event)"
          />
        </div>
      </div>
      <CommitteeAssignmentTable
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
