<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import Tabs from '@/components/ui/Tabs.vue'
import StatsCards from '@/components/data-list/StatsCards.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import RequestQueueFilters from '@/components/requests/RequestQueueFilters.vue'
import RequestQueueTable from '@/components/requests/RequestQueueTable.vue'
import PreparingRequestsPanel from '@/components/requests/PreparingRequestsPanel.vue'
import AssignAssessorDialog from '@/components/requests/AssignAssessorDialog.vue'
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
import { followedPathsOf, pathCountOf } from '@/utils/requestPaths'
import { localizedText } from '@/utils/localizedText'

const CYCLE_LIST_SIZE = 50

const { t, locale } = useI18n()
const { can } = usePermissions()
const auth = useAuthStore()
const paths = computed(() => followedPathsOf(auth.user))
const queue = useRequestQueue(paths)
const { items, status, errorMessage, load, filters, paginator, searchInput, stats, path } = queue
const { pagination, setPage } = paginator
const statCards = useQueueStatCards(stats)
const { stage } = useQueueStage()
const assessors = ref([])
const cycles = ref([])
const canAssign = computed(() => can(PERMISSIONS.REQUESTS_ASSIGN))
const assignment = ref(null)
const assignOpen = ref(false)

useHead({ title: () => t('requests.index.title') })

const currentCount = computed(() => pathCountOf(queue.pathCounts.value, path.value))

function stageTab(value, key, count) {
  const label = t(`requests.tabs.${key}`)
  return {
    value,
    label: count === undefined ? label : t('requests.tabs.withCount', { label, count }),
  }
}

const stageTabs = computed(() => [
  stageTab(QUEUE_STAGES.QUEUE, 'assignmentQueue', stats.value?.total ?? currentCount.value?.total),
  stageTab(QUEUE_STAGES.PREPARING, 'awaitingEntity', currentCount.value?.preparing),
])

function openAssign({ item, mode }) {
  assignment.value = { item, mode }
  assignOpen.value = true
}

async function loadAssessors() {
  if (!canAssign.value) return
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
const filterState = {
  filters: filters.filters,
  defaults: queue.filterDefaults,
  setFilter: queue.chooseFilter,
  sort: filters.sort,
  setSort: queue.setSort,
}
</script>

<template>
  <section class="requests-page">
    <header class="requests-page__header">
      <p class="requests-page__eyebrow">{{ t('requests.index.eyebrow') }}</p>
      <h1 class="requests-page__title">{{ t('requests.index.title') }}</h1>
      <p class="requests-page__subtitle">{{ t('requests.index.subtitle') }}</p>
    </header>
    <StatsCards :stats="statCards" :loading="!stats && status === 'loading'" />
    <Card>
      <Tabs v-model="stage" :tabs="stageTabs">
        <template #default="{ active }">
          <PreparingRequestsPanel v-if="active === QUEUE_STAGES.PREPARING" :path="path" />
          <template v-else>
            <ListToolbar
              v-model:search="searchInput"
              :search-placeholder="t('requests.index.searchPlaceholder')"
              :filter-state="filterState"
            >
              <template #filters="{ draft }">
                <RequestQueueFilters
                  :path="path"
                  :filters="draft.filters"
                  :sort="draft.sort.value"
                  :assessors="assessors"
                  :cycles="cycles"
                  @filter="draft.setFilter"
                  @sort="draft.setSort"
                />
              </template>
            </ListToolbar>
            <RequestQueueTable
              :items="items"
              :status="status"
              :error-message="errorMessage"
              :shows-region="path === ASSESSMENT_PATHS.ADVANCED"
              :can-assign="canAssign"
              @retry="load"
              @assign="openAssign"
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
    <AssignAssessorDialog
      v-if="assignment"
      v-model="assignOpen"
      :request-id="assignment.item.id"
      :mode="assignment.mode"
      :entity-name="localizedText(assignment.item.entity?.name, locale)"
      :path="assignment.item.path ?? ''"
      :path-label="assignment.item.pathLabel"
      @done="load"
    />
  </section>
</template>
