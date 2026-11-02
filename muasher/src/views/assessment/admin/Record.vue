<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
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
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  id: { type: String, required: true },
})

const { t, locale } = useI18n()
const { can } = usePermissions()
const assessmentId = computed(() => Number(props.id))
const { status, errorMessage, assessment, result, load } = useAssessmentRecord(assessmentId)

const entityName = computed(() => localizedText(assessment.value?.entity?.name, locale.value))

onMounted(load)
</script>

<template>
  <section class="assessment-record">
    <div v-if="status === 'loading'" class="assessment-record__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="5" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
    <EmptyState
      v-else-if="status === 'missing'"
      icon="fa-regular fa-clipboard"
      :title="t('assessment.result.missingTitle')"
      :message="t('assessment.result.missingMessage')"
    />

    <template v-else-if="assessment">
      <router-link
        v-if="assessment.entity && can(PERMISSIONS.ENTITIES_VIEW)"
        class="assessment-record__back"
        :to="{ name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: assessment.entity.id } }"
      >
        <i class="fa-solid fa-arrow-left u-flip-in-rtl" aria-hidden="true"></i>
        {{ t('assessment.staff.backToEntity') }}
      </router-link>

      <header class="assessment-record__header">
        <div>
          <p class="assessment-record__eyebrow">{{ t(`assessment.paths.${assessment.path}.title`) }}</p>
          <h1 class="assessment-record__title">
            {{ t('assessment.staff.recordTitle') }}
            <span class="u-ltr">{{ assessment.number }}</span>
          </h1>
        </div>
        <div class="assessment-record__badges">
          <StatusBadge :status="assessment.status" />
          <FrozenBadge v-if="assessment.isFrozen" />
        </div>
      </header>

      <AssessmentNotice
        v-if="assessment.isFrozen"
        variant="frozen"
        :title="t('assessment.staff.frozenTitle')"
        :message="t('assessment.staff.frozenMessage')"
      />
      <AssessmentNotice v-else variant="info" :title="t('assessment.staff.readOnlyTitle')" :message="t('assessment.staff.readOnlyMessage')" />

      <div class="assessment-record__grid">
        <Card :title="t('assessment.staff.detailsTitle')">
          <dl class="assessment-record__details">
            <div class="assessment-record__detail">
              <dt>{{ t('assessment.staff.entity') }}</dt>
              <dd>
                {{ entityName }}
                <span class="assessment-record__reference u-ltr">{{ assessment.entity?.reference }}</span>
              </dd>
            </div>
            <div class="assessment-record__detail">
              <dt>{{ t('assessment.staff.scale') }}</dt>
              <dd>{{ t(`assessment.self.scaleNames.${assessment.scaleKind}`) }}</dd>
            </div>
            <div v-if="assessment.criteriaUpdateInProgress" class="assessment-record__detail">
              <dt>{{ t('assessment.staff.criteria') }}</dt>
              <dd>{{ t('assessment.self.notices.criteriaUpdateTitle') }}</dd>
            </div>
          </dl>
        </Card>
        <Card :title="t('assessment.timeline.label')">
          <AssessmentTimeline :history="assessment.statusHistory" />
        </Card>
      </div>

      <ResultPendingNotice v-if="!result.available" :result="result" />
      <template v-else>
        <SelfResultSummary :result="result" :owner-view="false" />
        <SelfResultBreakdown :result="result" />
      </template>
    </template>
  </section>
</template>
