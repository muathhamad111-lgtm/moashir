<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import CriteriaBreadcrumb from '@/components/criteria/CriteriaBreadcrumb.vue'
import CriteriaChangeFilters from '@/components/criteria/review/CriteriaChangeFilters.vue'
import CriteriaChangeGroups from '@/components/criteria/review/CriteriaChangeGroups.vue'
import CriteriaVersionCorrections from '@/components/criteria/review/CriteriaVersionCorrections.vue'
import { useCriteriaVersionHistory } from '@/composables/useCriteriaVersionHistory'
import { useCriteriaVersionText } from '@/composables/useCriteriaVersionText'
import { useCriteriaMainNames } from '@/composables/useCriteriaMainNames'
import { useCriteria } from '@/composables/useCriteria'
import { countByTile, filterChanges } from '@/utils/criteriaChangeView'
import { ROUTE_NAMES } from '@/constants/routeNames'

// What a published version changed against the one it replaced (design §B.3.6), and its later text
// corrections. The same page serves every version listed in the versions tab.
const props = defineProps({
  versionId: { type: Number, required: true },
})

const { t } = useI18n()
const { localized } = useCriteria()
const history = useCriteriaVersionHistory()
const { versionNumber, dateOf } = useCriteriaVersionText()
const { mainNames, loadMainNames } = useCriteriaMainNames()

const tile = ref(null)
const query = ref('')

const version = computed(() => history.findVersion(props.versionId))
const publication = computed(() => history.publicationOf(props.versionId))
const corrections = computed(() => history.correctionsOf(props.versionId))
const changes = computed(() => publication.value?.changes ?? [])
const counts = computed(() => countByTile(changes.value))
const visible = computed(() =>
  filterChanges(changes.value, {
    tile: tile.value,
    query: query.value,
    textOf: (change) => `${change.code} ${localized(change.label)}`,
  }),
)

const crumbs = computed(() => [
  { label: t('criteria.index.title'), to: { name: ROUTE_NAMES.ADMIN_CRITERIA } },
  { label: t('criteria.pageTabs.versions'), to: { name: ROUTE_NAMES.ADMIN_CRITERIA_VERSIONS } },
  { label: versionNumber(version.value) },
])

// «من الإصدار 5 إلى الإصدار 6 · نُشر في … · scope · by»: the facts that are known, in one line.
const facts = computed(() => {
  const event = publication.value
  if (!event) return []
  return [
    t('criteria.changes.fromTo', {
      from: versionNumber(event.fromVersion),
      to: versionNumber(event.toVersion),
    }),
    t('criteria.changes.publishedOn', { date: dateOf(event.createdAt) }),
    t(`criteria.publish.scopes.${event.scope}.title`),
    event.editedBy?.name ? t('criteria.changes.by', { name: event.editedBy.name }) : '',
  ].filter(Boolean)
})

function load() {
  history.load()
  loadMainNames(props.versionId)
}

watch(() => props.versionId, load)
onMounted(load)
</script>

<template>
  <section class="criteria-page criteria-version-changes">
    <CriteriaBreadcrumb :items="crumbs" />
    <div v-if="history.status.value === 'loading'" class="criteria-page__loading">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="6" />
    </div>
    <ErrorState v-else-if="history.status.value === 'error'" @retry="load" />
    <EmptyState
      v-else-if="!version"
      :title="t('criteria.changes.notFoundTitle')"
      :message="t('criteria.changes.notFoundMessage')"
      icon="hierarchy"
    />
    <template v-else>
      <header class="criteria-page__header">
        <div>
          <h1 class="criteria-page__title">
            {{ t('criteria.changes.title', { number: version.versionNumber }) }}
          </h1>
          <p v-if="facts.length" class="criteria-page__subtitle">{{ facts.join(' · ') }}</p>
          <p v-if="publication?.reason" class="criteria-page__subtitle">
            {{ t('criteria.versions.reason') }} <bdi>{{ publication.reason }}</bdi>
          </p>
        </div>
      </header>

      <template v-if="changes.length">
        <CriteriaChangeFilters v-model:tile="tile" v-model:query="query" :counts="counts" />
        <CriteriaChangeGroups v-if="visible.length" :changes="visible" :main-names="mainNames" />
        <p v-else class="criteria-details__note" role="status">
          {{ t('criteria.changes.noMatch') }}
        </p>
      </template>
      <p v-else class="criteria-details__note">
        {{ t(`criteria.changes.${publication ? 'noStructural' : 'noComparison'}`) }}
      </p>

      <CriteriaVersionCorrections v-if="corrections.length" :corrections="corrections" />
    </template>
  </section>
</template>
