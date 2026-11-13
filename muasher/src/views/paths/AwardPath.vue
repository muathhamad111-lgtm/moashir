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

const { t } = useI18n()
const store = useEntityPathsStore()
const { paths, status } = storeToRefs(store)
const summaries = usePathSummaries(paths)
const summary = computed(() => summaries.value?.[PATH_KEYS.AWARD] ?? null)
const award = computed(() => paths.value?.award ?? null)
const visible = computed(() => award.value && summary.value?.state !== AWARD_STATES.HIDDEN)
const requests = usePathRequests(PATH_KEYS.AWARD)

useHead({ title: () => t('paths.names.award') })

function reload() {
  store.load().catch(() => null)
}

onMounted(reload)
</script>

<template>
  <section class="path-page" data-testid="path-page-award">
    <PathHeader :path-key="PATH_KEYS.AWARD" :lead="t('paths.pages.award.lead')">
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
            <PathPrimaryAction :action="summary.action" />
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
      <ErrorState
        v-if="status === 'error' && !paths"
        :message="t('paths.pages.loadError')"
        @retry="reload"
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
