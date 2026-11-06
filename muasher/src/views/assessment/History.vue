<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import PortalAreaHeader from '@/components/portal/PortalAreaHeader.vue'
import AssessmentHistoryItem from '@/components/assessment/AssessmentHistoryItem.vue'
import CurrentAssessmentCard from '@/components/assessment/portal/CurrentAssessmentCard.vue'
import AssessmentStartCards from '@/components/assessment/portal/AssessmentStartCards.vue'
import AssessmentResultRow from '@/components/assessment/portal/AssessmentResultRow.vue'
import { useAssessmentsHub } from '@/composables/useAssessmentsHub'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ASSESSMENT_PATHS, ASSESSMENT_TABS } from '@/constants/assessment'

const PANEL_ID = 'assessments-panel'

const { t } = useI18n()
const { can } = usePermissions()
const hub = useAssessmentsHub()

useHead({ title: () => t('portal.assessments.title') })

const items = computed(() => hub.items.value)
const active = computed(() => hub.accredited.value?.active ?? null)
const pendingPayment = computed(() => hub.accredited.value?.awaitingPayment ?? null)
const hasOpen = (path) => items.value.some((item) => item.path === path)
const canStartSelf = computed(
  () => can(PERMISSIONS.ASSESSMENT_SELF_MANAGE) && !hasOpen(ASSESSMENT_PATHS.SELF),
)
const canRequestAccredited = computed(
  () =>
    can(PERMISSIONS.ASSESSMENT_ACCREDITED_MANAGE) &&
    !hasOpen(ASSESSMENT_PATHS.ACCREDITED) &&
    !pendingPayment.value,
)
const showsStart = computed(
  () => canStartSelf.value || canRequestAccredited.value || Boolean(pendingPayment.value),
)

function progressOf(item) {
  return active.value?.id === item.id ? (active.value.progress ?? null) : null
}
</script>

<template>
  <section class="portal-area assessments-hub">
    <PortalAreaHeader
      band
      :eyebrow="t('portal.assessments.eyebrow')"
      :title="t('portal.assessments.title')"
      :subtitle="t('portal.assessments.subtitle')"
      :tabs="hub.tabs.value"
      :tab="hub.tab.value"
      :tabs-label="t('portal.assessments.tabsLabel')"
      :panel-id="PANEL_ID"
      @update:tab="hub.tab.value = $event"
    />

    <div class="portal-area__body">
      <div
        :id="PANEL_ID"
        class="portal-area__panel"
        role="tabpanel"
        :aria-labelledby="`${PANEL_ID}-tab-${hub.tab.value}`"
      >
        <Skeleton v-if="hub.status.value === 'loading'" variant="block" :count="2" />
        <ErrorState
          v-else-if="hub.status.value === 'error'"
          :message="hub.errorMessage.value"
          @retry="hub.load"
        />

        <template v-else-if="hub.tab.value === ASSESSMENT_TABS.CURRENT">
          <CurrentAssessmentCard
            v-for="item in items"
            :key="item.id"
            :item="item"
            :progress="progressOf(item)"
          />
          <AssessmentStartCards
            v-if="showsStart"
            :can-start-self="canStartSelf"
            :can-request-accredited="canRequestAccredited"
            :pending-payment="pendingPayment"
          />
          <EmptyState
            v-if="!items.length && !showsStart"
            icon="doc"
            :title="t('portal.assessments.empty.current.title')"
            :message="t('portal.assessments.empty.current.message')"
          />
        </template>

        <template v-else>
          <EmptyState
            v-if="!items.length"
            icon="doc"
            :title="t(`portal.assessments.empty.${hub.tab.value}.title`)"
            :message="t(`portal.assessments.empty.${hub.tab.value}.message`)"
          />
          <ul v-else-if="hub.tab.value === ASSESSMENT_TABS.HISTORY" class="history__list">
            <AssessmentHistoryItem v-for="item in items" :key="item.id" :item="item" />
          </ul>
          <ul v-else class="assessments-hub__results">
            <AssessmentResultRow v-for="item in items" :key="item.id" :item="item" />
          </ul>
          <Pagination
            v-if="hub.lastPage.value > 1"
            :page="hub.page.value"
            :last-page="hub.lastPage.value"
            @update:page="hub.setPage"
          />
        </template>
      </div>
    </div>
  </section>
</template>
