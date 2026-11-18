<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import Badge from '@/components/ui/Badge.vue'
import CriteriaBreadcrumb from '@/components/criteria/CriteriaBreadcrumb.vue'
import CriteriaChangeList from '@/components/criteria/CriteriaChangeList.vue'
import { useTable } from '@/composables/useTable'
import { usePagination } from '@/composables/usePagination'
import { useCriteria } from '@/composables/useCriteria'
import { criteriaService } from '@/services/criteriaService'
import { formatDate } from '@/utils/datetime'
import { ROUTE_NAMES } from '@/constants/routeNames'

const PER_PAGE = 20

const { t, locale } = useI18n()
const { localized } = useCriteria()
const paginator = usePagination({ perPage: PER_PAGE })
const { items, status, errorMessage, load } = useTable({
  fetcher: (params) => criteriaService.listEditEvents(params),
  pagination: paginator,
})

const crumbs = computed(() => [
  { label: t('criteria.breadcrumb.mains'), to: { name: ROUTE_NAMES.ADMIN_CRITERIA } },
  { label: t('criteria.history.title') },
])

const columns = computed(() => [
  { key: 'date', label: t('criteria.history.columns.date') },
  { key: 'versions', label: t('criteria.history.columns.versions') },
  { key: 'kind', label: t('criteria.history.columns.kind') },
  { key: 'changes', label: t('criteria.history.columns.changes') },
  { key: 'impact', label: t('criteria.history.columns.impact') },
  { key: 'editor', label: t('criteria.history.columns.editor') },
])

function dateOf(value) {
  return formatDate(value, locale.value, {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Asia/Riyadh',
  })
}

function versionName(version) {
  if (!version) return ''
  const label = localized(version.label)
  return label
    ? t('criteria.history.versionWithLabel', { number: version.versionNumber, label })
    : t('criteria.history.version', { number: version.versionNumber })
}

function kindOf(event) {
  return event.kind === 'text_correction'
    ? t('criteria.history.kinds.textCorrection')
    : t(`criteria.publish.scopes.${event.scope}.title`)
}
</script>

<template>
  <section class="criteria-page">
    <CriteriaBreadcrumb :items="crumbs" />
    <header class="criteria-page__header">
      <div>
        <h1 class="criteria-page__title">{{ t('criteria.history.title') }}</h1>
        <p class="criteria-page__subtitle">{{ t('criteria.history.subtitle') }}</p>
      </div>
    </header>

    <DataTable
      :columns="columns"
      :items="items"
      :status="status"
      :error-message="errorMessage"
      :empty-title="t('criteria.history.emptyTitle')"
      :empty-message="t('criteria.history.emptyMessage')"
      @retry="load"
    >
      <template #cell-date="{ item }">{{ dateOf(item.createdAt) }}</template>
      <template #cell-versions="{ item }">
        <span>{{ versionName(item.fromVersion) }}</span>
        <span v-if="item.toVersion" class="criteria-page__sub-count">
          {{ t('criteria.history.to', { version: versionName(item.toVersion) }) }}
        </span>
      </template>
      <template #cell-kind="{ item }">
        <Badge :variant="item.kind === 'text_correction' ? 'muted' : 'info'">{{
          kindOf(item)
        }}</Badge>
        <span v-if="item.reason" class="criteria-page__sub-count">{{
          t('criteria.history.reason', { reason: item.reason })
        }}</span>
      </template>
      <template #cell-changes="{ item }">
        <details v-if="item.changes.length">
          <summary>
            {{
              t(
                'criteria.publish.preview.summary',
                { count: item.changeCount, scoreAffecting: item.scoreAffectingCount },
                item.changeCount,
              )
            }}
          </summary>
          <CriteriaChangeList :changes="item.changes" />
        </details>
        <span v-else>{{ t('criteria.history.noChanges') }}</span>
      </template>
      <template #cell-impact="{ item }">{{
        t('criteria.history.affected', { count: item.affectedOpenCount }, item.affectedOpenCount)
      }}</template>
      <template #cell-editor="{ item }">{{
        item.editedBy?.name ?? t('criteria.history.unknownEditor')
      }}</template>
    </DataTable>

    <Pagination
      v-if="status === 'success' && paginator.pagination.lastPage > 1"
      :page="paginator.pagination.page"
      :last-page="paginator.pagination.lastPage"
      @update:page="paginator.setPage($event)"
    />
  </section>
</template>
