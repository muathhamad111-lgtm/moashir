<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import Tabs from '@/components/ui/Tabs.vue'
import PathBadge from '@/components/ui/PathBadge.vue'
import StatsCards from '@/components/data-list/StatsCards.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import RequestPathSwitch from '@/components/requests/RequestPathSwitch.vue'
import RequestQueueFilters from '@/components/requests/RequestQueueFilters.vue'
import RequestQueueTable from '@/components/requests/RequestQueueTable.vue'
import PreparingRequestsPanel from '@/components/requests/PreparingRequestsPanel.vue'
import { useRequestQueue } from '@/composables/useRequestQueue'
import { useQueueStatCards } from '@/composables/useQueueStatCards'
import { useQueueStage } from '@/composables/useQueueStage'
import { usePermissions } from '@/composables/usePermissions'
import { useAuthStore } from '@/stores/auth'
import { requestsService } from '@/services/requestsService'
import { awardCycleService } from '@/services/awardCycleService'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { CYCLE_STATUSES } from '@/constants/advanced'
import { QUEUE_STAGES } from '@/constants/accredited'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { followedPathsOf, pathCountOf } from '@/utils/requestPaths'

const CYCLE_LIST_SIZE = 50

const { t } = useI18n()
const { can } = usePermissions()
const auth = useAuthStore()
const paths = computed(() => followedPathsOf(auth.user))
const queue = useRequestQueue(paths)
const { items, status, errorMessage, load, filters, paginator, searchInput, stats, path } = queue
const { pagination, setPage } = paginator
const statCards = useQueueStatCards(stats)
const { stage, stageQuery } = useQueueStage()
const assessors = ref([])
const cycles = ref([])

useHead({ title: () => t('requests.index.title') })

const switchEntries = computed(() =>
  paths.value.map((value) => {
    const entry = pathCountOf(queue.pathCounts.value, value)
    return {
      path: value,
      pathLabel: entry?.pathLabel ?? null,
      countLabel: entry ? t('nav.pathCounts.queue', { count: entry.awaitingAssignment }) : '',
    }
  }),
)
const currentCount = computed(() => pathCountOf(queue.pathCounts.value, path.value))
const currentLabel = computed(() => currentCount.value?.pathLabel ?? null)
const keptQuery = computed(() => ({ ...queue.linkQuery.value, ...stageQuery.value }))

function stageTab(value, key, count) {
  const label = t(`requests.tabs.${key}`)
  return {
    value,
    label: count === undefined ? label : t('requests.tabs.withCount', { label, count }),
  }
}

const stageTabs = computed(() => [
  stageTab(QUEUE_STAGES.QUEUE, 'assignmentQueue', currentCount.value?.total),
  stageTab(QUEUE_STAGES.PREPARING, 'awaitingEntity', currentCount.value?.preparing),
])

async function loadAssessors() {
  if (!can(PERMISSIONS.REQUESTS_ASSIGN)) return
  const { data } = await requestsService.listAssessors({ path: path.value }).catch(() => ({}))
  assessors.value = data ?? []
}

async function loadCycles() {
  if (!can(PERMISSIONS.AWARD_CYCLES_VIEW) || !paths.value.includes(ASSESSMENT_PATHS.ADVANCED))
    return
  const { data } = await awardCycleService.list({ perPage: CYCLE_LIST_SIZE }).catch(() => ({}))
  cycles.value = (data ?? []).filter((cycle) => cycle.status !== CYCLE_STATUSES.DRAFT)
}

watch(path, loadAssessors)
onMounted(() => Promise.all([loadAssessors(), loadCycles()]))
</script>

<template>
  <section class="requests-page">
    <header class="requests-page__header">
      <p class="requests-page__eyebrow">{{ t('requests.index.eyebrow') }}</p>
      <div class="requests-page__title-row">
        <h1 class="requests-page__title">{{ t('requests.index.title') }}</h1>
        <PathBadge :path="path" :label="currentLabel" />
      </div>
      <p class="requests-page__subtitle">{{ t('requests.index.subtitle') }}</p>
    </header>
    <RequestPathSwitch
      v-if="paths.length > 1"
      :entries="switchEntries"
      :current="path"
      :route-name="ROUTE_NAMES.REQUESTS_INDEX"
      :label="t('requests.index.pathSwitchLabel')"
      :kept-query="keptQuery"
    />
    <StatsCards :stats="statCards" :loading="!stats && status === 'loading'" />
    <Card>
      <Tabs v-model="stage" :tabs="stageTabs">
        <template #default="{ active }">
          <PreparingRequestsPanel v-if="active === QUEUE_STAGES.PREPARING" :path="path" />
          <template v-else>
            <ListToolbar
              v-model:search="searchInput"
              :search-placeholder="t('requests.index.searchPlaceholder')"
            >
              <template #filters>
                <RequestQueueFilters
                  :path="path"
                  :filters="filters.filters"
                  :sort="filters.sort.value"
                  :assessors="assessors"
                  :cycles="cycles"
                  @filter="queue.chooseFilter"
                  @sort="queue.setSort"
                />
              </template>
            </ListToolbar>
            <RequestQueueTable
              :items="items"
              :status="status"
              :error-message="errorMessage"
              :shows-region="path === ASSESSMENT_PATHS.ADVANCED"
              @retry="load"
            />
            <Pagination
              v-if="status === 'success' && pagination.lastPage > 1"
              :page="pagination.page"
              :last-page="pagination.lastPage"
              @update:page="setPage($event)"
            />
          </template>
        </template>
      </Tabs>
    </Card>
  </section>
</template>
