<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import AdvancedEligibilityList from './AdvancedEligibilityList.vue'
import { ADVANCED_STATUSES as S } from '@/constants/advanced'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { RESULT_STATUSES as R } from '@/constants/committee'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  request: { type: Object, default: null },
  eligibility: { type: Object, required: true },
  applying: { type: Boolean, default: false },
  deleting: { type: Boolean, default: false },
})

const emit = defineEmits(['apply', 'delete'])
const { t } = useI18n()

const UNDER_REVIEW = [
  S.AWAITING_ASSIGNMENT,
  S.ASSIGNED,
  S.UNDER_INITIAL_ASSESSMENT,
  S.UNDER_REVIEW,
  S.AWAITING_FILTERING,
]
const CLOSED = {
  [S.EXPIRED]: 'expired',
  [S.DECLINED]: 'declined',
  [S.WITHDRAWN]: 'withdrawn',
  [S.NOT_QUALIFIED]: 'notQualified',
}

const ANNOUNCED = [R.NON_WINNER, R.NATIONAL_WINNER, R.NATIONAL_NON_WINNER]
const WITH_RESULT = [S.NOT_QUALIFIED, ...ANNOUNCED]

function stateOfRequest(status) {
  if (status === S.REJECTED_CONDITIONS) return 'rejected'
  if (ANNOUNCED.includes(status)) return 'result'
  if (status === S.DRAFT) return 'draft'
  if (status === S.NEEDS_COMPLETION) return 'completion'
  if (UNDER_REVIEW.includes(status)) return 'review'
  return CLOSED[status] ?? 'other'
}

const state = computed(() => {
  if (props.request) return stateOfRequest(props.request.status)
  if (props.eligibility.eligible) return 'apply'
  return props.eligibility.windowOpen ? 'notEligible' : 'windowClosed'
})
const canApply = computed(
  () => ['apply', 'rejected'].includes(state.value) && props.eligibility.eligible,
)
const failures = computed(() =>
  state.value === 'rejected'
    ? props.request.conditionFailures
    : props.eligibility.conditions.filter((check) => !check.passed),
)
const variant = computed(
  () =>
    ({ rejected: 'danger', notEligible: 'danger', completion: 'warning', windowClosed: 'info' })[
      state.value
    ] ?? 'info',
)
const resultRoute = computed(() =>
  props.request && WITH_RESULT.includes(props.request.status)
    ? { name: ROUTE_NAMES.ASSESSMENT_ADVANCED_RESULT, params: { id: props.request.id } }
    : null,
)
const formRoute = computed(() =>
  props.request
    ? { name: ROUTE_NAMES.ASSESSMENT_ADVANCED_FORM, params: { id: props.request.id } }
    : null,
)
</script>

<template>
  <section class="advanced-request" :aria-label="t('advanced.request.label')">
    <header v-if="request" class="advanced-request__head">
      <p class="advanced-request__number">
        <bdi dir="ltr">{{ request.number }}</bdi>
      </p>
      <StatusBadge :status="request.status" />
    </header>
    <AssessmentNotice
      :variant="variant"
      :title="t(`advanced.request.states.${state}.title`)"
      :message="t(`advanced.request.states.${state}.message`)"
    />
    <AdvancedEligibilityList
      v-if="['rejected', 'notEligible'].includes(state) && failures.length"
      :conditions="failures"
    />
    <p v-if="request?.submittedAt" class="advanced-request__meta">
      {{ t('advanced.request.submittedAt') }}
      <DateStamp :value="request.submittedAt" :with-time="false" with-hijri />
    </p>
    <div class="advanced-request__actions">
      <BaseButton
        v-if="canApply"
        variant="primary"
        icon="send"
        :loading="applying"
        @click="emit('apply')"
      >
        {{ state === 'rejected' ? t('advanced.request.applyAgain') : t('advanced.request.apply') }}
      </BaseButton>
      <RouterLink
        v-if="state === 'draft' || state === 'completion'"
        class="btn btn--primary btn--md advanced-request__link"
        :to="formRoute"
      >
        <AppIcon name="pen-line" />
        {{ state === 'draft' ? t('advanced.request.continue') : t('advanced.request.complete') }}
      </RouterLink>
      <RouterLink
        v-if="resultRoute"
        class="btn btn--primary btn--md advanced-request__link"
        :to="resultRoute"
      >
        <AppIcon name="trophy" />
        {{ t('advanced.request.viewResult') }}
      </RouterLink>
      <BaseButton
        v-if="state === 'draft'"
        variant="danger"
        icon="trash"
        :loading="deleting"
        @click="emit('delete')"
      >
        {{ t('advanced.draft.delete') }}
      </BaseButton>
    </div>
  </section>
</template>
