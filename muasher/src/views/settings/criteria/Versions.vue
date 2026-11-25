<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import CriteriaPageTabs from '@/components/criteria/CriteriaPageTabs.vue'
import { useCriteriaVersionHistory } from '@/composables/useCriteriaVersionHistory'
import { useCriteriaVersionText } from '@/composables/useCriteriaVersionText'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { CRITERIA_VERSION_STATUSES as STATUS } from '@/constants/criteria'

// The versions tab (UX-A3, CR-8): every version with when, how and by whom it was published.
const { t } = useI18n()
const history = useCriteriaVersionHistory()
const { versionName, dateOf } = useCriteriaVersionText()

const STATUS_VARIANTS = { [STATUS.DRAFT]: 'warning', [STATUS.PUBLISHED]: 'success' }

const columns = computed(() => [
  { key: 'version', label: t('criteria.versions.columns.version') },
  { key: 'published', label: t('criteria.versions.columns.published'), date: true },
  { key: 'scope', label: t('criteria.versions.columns.scope') },
  { key: 'affected', label: t('criteria.versions.columns.affected') },
  { key: 'actions', label: t('criteria.versions.columns.actions') },
])

const rows = computed(() =>
  [...history.versions.value]
    .sort((first, second) => second.versionNumber - first.versionNumber)
    .map((version) => {
      const publication = history.publicationOf(version.id)
      const corrections = history.correctionsOf(version.id)
      return {
        id: version.id,
        version,
        publication,
        corrections: corrections.length,
        hasChanges: Boolean(publication?.changes.length || corrections.length),
      }
    }),
)

function changesLink(row) {
  return { name: ROUTE_NAMES.ADMIN_CRITERIA_VERSION_CHANGES, params: { versionId: row.id } }
}

function workspaceLink(row) {
  return { name: ROUTE_NAMES.ADMIN_CRITERIA, query: { version: row.id } }
}

onMounted(history.load)
</script>

<template>
  <section class="criteria-page">
    <header class="criteria-page__header">
      <div>
        <h1 class="criteria-page__title">{{ t('criteria.index.title') }}</h1>
        <p class="criteria-page__subtitle">{{ t('criteria.versions.subtitle') }}</p>
      </div>
    </header>
    <CriteriaPageTabs />

    <DataTable
      table-key="criteria-versions"
      :columns="columns"
      :items="rows"
      :status="history.status.value"
      :empty-title="t('criteria.index.noVersionsTitle')"
      :empty-message="t('criteria.index.noVersionsMessage')"
      @retry="history.load"
    >
      <template #cell-version="{ item }">
        <span class="criteria-versions__name">{{ versionName(item.version) }}</span>
        <Badge :variant="STATUS_VARIANTS[item.version.status] ?? 'muted'">
          {{ t(`criteria.versions.status.${item.version.status}`) }}
        </Badge>
      </template>
      <template #cell-published="{ item }">
        <span>{{ dateOf(item.version.publishedAt) }}</span>
        <span v-if="item.publication?.editedBy?.name" class="criteria-page__sub-count">
          {{ item.publication.editedBy.name }}
        </span>
      </template>
      <template #cell-scope="{ item }">
        <span>{{
          item.publication ? t(`criteria.publish.scopes.${item.publication.scope}.title`) : '—'
        }}</span>
        <span v-if="item.publication?.reason" class="criteria-page__sub-count">
          {{ t('criteria.versions.reason') }} <bdi>{{ item.publication.reason }}</bdi>
        </span>
      </template>
      <template #cell-affected="{ item }">
        <template v-if="item.publication">
          {{
            t(
              'criteria.versions.affected',
              { count: item.publication.affectedOpenCount },
              item.publication.affectedOpenCount,
            )
          }}
        </template>
        <template v-else>—</template>
      </template>
      <template #cell-actions="{ item }">
        <div class="criteria-versions__actions">
          <BaseButton v-if="item.hasChanges" variant="secondary" size="sm" :to="changesLink(item)">
            {{ t('criteria.versions.whatChanged') }}
          </BaseButton>
          <BaseButton variant="ghost" size="sm" :to="workspaceLink(item)">
            {{ t('criteria.versions.open') }}
          </BaseButton>
        </div>
      </template>
    </DataTable>
  </section>
</template>
