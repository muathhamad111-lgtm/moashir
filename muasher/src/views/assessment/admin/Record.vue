<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Tabs from '@/components/ui/Tabs.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import PathBadge from '@/components/ui/PathBadge.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import FrozenBadge from '@/components/entities/FrozenBadge.vue'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import AssessmentTimeline from '@/components/assessment/AssessmentTimeline.vue'
import ResultPendingNotice from '@/components/assessment/ResultPendingNotice.vue'
import SelfResultSummary from '@/components/assessment/self/SelfResultSummary.vue'
import SelfResultBreakdown from '@/components/assessment/self/SelfResultBreakdown.vue'
import { useAssessmentRecord } from '@/composables/useAssessmentRecord'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'
import AppIcon from '@/components/ui/AppIcon.vue'

// The self-assessment record on the same page shape as the request record (design §B.3.4, UX-5): the
// number as the title with the path and status beside it, the data, then the overview and the log
// as tabs.
const TABS = Object.freeze({ OVERVIEW: 'overview', LOG: 'log' })

const props = defineProps({
  id: { type: String, required: true },
})

const { t, locale } = useI18n()
const { can } = usePermissions()
const assessmentId = computed(() => Number(props.id))
const { status, errorMessage, assessment, result, load } = useAssessmentRecord(assessmentId)

const entityName = computed(() => localizedText(assessment.value?.entity?.name, locale.value))
const readOnlyMessage = computed(() =>
  t(`assessment.staff.readOnlyMessages.${assessment.value?.path ?? ASSESSMENT_PATHS.SELF}`),
)

const tab = ref(TABS.OVERVIEW)
const tabs = computed(() => [
  { value: TABS.OVERVIEW, label: t('requests.detail.tabs.overview') },
  { value: TABS.LOG, label: t('requests.detail.tabs.log') },
])

onMounted(load)
</script>

<template>
  <section class="request-detail assessment-record">
    <div v-if="status === 'loading'" class="request-detail__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="5" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
    <EmptyState
      v-else-if="status === 'missing'"
      icon="doc"
      :title="t('assessment.result.missingTitle')"
      :message="t('assessment.result.missingMessage')"
    />

    <template v-else-if="assessment">
      <header class="request-detail__header">
        <router-link
          v-if="assessment.entity && can(PERMISSIONS.ENTITIES_VIEW)"
          class="request-detail__back"
          :to="{ name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: assessment.entity.id } }"
        >
          <AppIcon name="arrow-left" />
          {{ t('assessment.staff.backToEntity') }}
        </router-link>
        <div class="request-detail__heading">
          <div class="request-detail__identity">
            <h1 class="request-detail__title">
              <span class="assessment-record__title-label">{{
                t('assessment.staff.recordTitle')
              }}</span>
              <bdi dir="ltr">{{ assessment.number }}</bdi>
            </h1>
            <p class="request-detail__number assessment-record__badges">
              <PathBadge
                v-if="assessment.path"
                :path="assessment.path"
                :label="assessment.pathLabel"
              />
              <StatusBadge :status="assessment.status" />
              <FrozenBadge v-if="assessment.isFrozen" />
            </p>
          </div>
        </div>
      </header>

      <AssessmentNotice
        v-if="assessment.isFrozen"
        variant="frozen"
        :title="t('assessment.staff.frozenTitle')"
        :message="t('assessment.staff.frozenMessage')"
      />
      <AssessmentNotice
        v-else
        variant="info"
        :title="t('assessment.staff.readOnlyTitle')"
        :message="readOnlyMessage"
      />

      <Card class="request-detail__facts">
        <template #header>
          <h2 class="card__title-text">{{ t('assessment.staff.detailsTitle') }}</h2>
        </template>
        <dl class="assessment-record__details">
          <div class="assessment-record__detail">
            <dt>{{ t('assessment.staff.entity') }}</dt>
            <dd>
              {{ entityName }}
              <span class="assessment-record__reference u-ltr">{{
                assessment.entity?.reference
              }}</span>
            </dd>
          </div>
          <div class="assessment-record__detail">
            <dt>{{ t('assessment.staff.scale') }}</dt>
            <dd>
              {{
                assessment.scaleKind
                  ? t(`assessment.self.scaleNames.${assessment.scaleKind}`)
                  : t('assessment.self.scaleByCriterion')
              }}
            </dd>
          </div>
          <div v-if="assessment.criteriaUpdateInProgress" class="assessment-record__detail">
            <dt>{{ t('assessment.staff.criteria') }}</dt>
            <dd>{{ t('assessment.self.notices.criteriaUpdateTitle') }}</dd>
          </div>
        </dl>
      </Card>

      <Tabs v-model="tab" :tabs="tabs" class="request-detail__tabs">
        <template #default="{ active }">
          <div v-if="active === TABS.OVERVIEW" class="request-overview">
            <ResultPendingNotice v-if="!result.available" :result="result" />
            <template v-else>
              <SelfResultSummary :result="result" :owner-view="false" />
              <SelfResultBreakdown :result="result" />
            </template>
          </div>
          <Card v-else :title="t('assessment.timeline.label')">
            <AssessmentTimeline :history="assessment.statusHistory" />
          </Card>
        </template>
      </Tabs>
    </template>
  </section>
</template>
