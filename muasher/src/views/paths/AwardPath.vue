<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import { storeToRefs } from 'pinia'
import { useEntityPathsStore } from '@/stores/entityPaths'
import { usePathSummaries } from '@/composables/usePathSummaries'
import { usePathRequests } from '@/composables/usePathRequests'
import { PATH_KEYS } from '@/constants/entityPaths'
import { AWARD_STATES } from '@/utils/pathStates'
import ErrorState from '@/components/feedback/ErrorState.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import PathHeader from '@/components/paths/PathHeader.vue'
import PathHeaderCard from '@/components/paths/PathHeaderCard.vue'
import PathPrice from '@/components/paths/PathPrice.vue'
import PathPrimaryAction from '@/components/paths/PathPrimaryAction.vue'
import AwardCycleCard from '@/components/paths/AwardCycleCard.vue'
import PathRequestsList from '@/components/paths/PathRequestsList.vue'
import AwardOpenCyclesList from '@/components/paths/AwardOpenCyclesList.vue'
import CycleDescriptionText from '@/components/award/CycleDescriptionText.vue'
import { usePathNames } from '@/composables/usePathNames'
import { useAwardApplication } from '@/composables/useAwardApplication'
import { useAwardCycleChoice } from '@/composables/useAwardCycleChoice'
import { useRouter } from 'vue-router'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const { pathName } = usePathNames()
const store = useEntityPathsStore()
const { paths, status } = storeToRefs(store)
const summaries = usePathSummaries(paths)
const summary = computed(() => summaries.value?.[PATH_KEYS.AWARD] ?? null)
const award = computed(() => paths.value?.award ?? null)
const visible = computed(() => award.value && summary.value?.state !== AWARD_STATES.HIDDEN)
const openCycles = computed(() => award.value?.openCycles ?? [])
const cycleChoice = useAwardCycleChoice(
  openCycles,
  computed(() => award.value?.cycle?.id ?? null),
)
const requests = usePathRequests(PATH_KEYS.AWARD)
const router = useRouter()
const application = useAwardApplication()
const { applying } = application

useHead({ title: () => pathName('advanced') })

function reload() {
  store.load().catch(() => null)
}

// Applying happens here, inside the portal: the draft opens in the form, a paid cycle goes to its checkout, and
// a refusal reloads the path so its header shows the recorded reason (AJ-04). With several open cycles each card
// applies to its own cycle; the header applies to the cycle it describes (the only open one by default).
async function applyTo(cycleId) {
  const request = await application.apply(cycleId)
  if (request) {
    await router.push({ name: ROUTE_NAMES.ASSESSMENT_ADVANCED_FORM, params: { id: request.id } })
    return
  }
  if (!application.rejection.value) return
  reload()
  requests.load()
}

function applyForAward() {
  return applyTo(award.value?.cycle?.id)
}

onMounted(reload)
</script>

<template>
  <section class="path-page" data-testid="path-page-award">
    <PathHeader :path-key="PATH_KEYS.AWARD">
      <Skeleton v-if="status === 'loading' && !paths" variant="block" :count="3" />
      <template v-else-if="visible">
        <AwardCycleCard :award="award" />
        <PathHeaderCard
          :eyebrow="t('paths.pages.status')"
          test-id="path-status-card"
          :data-state="summary.state"
        >
          <p class="path-header-card__text">{{ summary.status }}</p>
          <p
            v-if="summary.state === AWARD_STATES.LATE_PAYMENT"
            class="path-header-card__note"
            data-testid="path-late-payment"
          >
            {{ t('paths.pages.award.latePaymentNote') }}
          </p>
          <template #actions>
            <PathPrimaryAction :action="summary.action" :busy="applying" @run="applyForAward" />
          </template>
        </PathHeaderCard>
        <PathHeaderCard :eyebrow="t('paths.pages.fee')">
          <PathPrice :pricing="award.pricing" detailed />
        </PathHeaderCard>
      </template>
      <PathHeaderCard
        v-else-if="summary"
        :eyebrow="t('paths.pages.status')"
        test-id="path-unavailable-card"
      >
        <p class="path-header-card__text">{{ t('paths.summary.unavailable') }}</p>
      </PathHeaderCard>
    </PathHeader>
    <div class="path-page__body">
      <CycleDescriptionText v-if="visible" :cycle="award.cycle" />
      <ErrorState
        v-if="status === 'error' && !paths"
        :message="t('paths.pages.loadError')"
        @retry="reload"
      />
      <AwardOpenCyclesList
        v-if="visible && cycleChoice.showChooser.value"
        :cycles="openCycles"
        :selected-id="cycleChoice.selectedId.value"
        :applying="applying"
        @apply="applyTo"
      />
      <PathRequestsList
        :path="PATH_KEYS.AWARD"
        :rows="requests.rows.value"
        :status="requests.status.value"
        :page="requests.page.value"
        :last-page="requests.lastPage.value"
        @page="requests.load"
        @retry="requests.load()"
      />
    </div>
  </section>
</template>
