<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import SelfStartCard from '@/components/assessment/self/SelfStartCard.vue'
import SelfAssessmentWorkspace from '@/components/assessment/self/SelfAssessmentWorkspace.vue'
import { ENTRY_STATES, useSelfAssessmentEntry } from '@/composables/useSelfAssessmentEntry'
import { useAssessmentResultHandoffStore } from '@/stores/assessmentResultHandoff'
import { SELF_ASSESSMENT_CODES as CODES } from '@/constants/assessment'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'

const BLOCKED_KEYS = {
  [CODES.ENTITY_NOT_ACTIVE]: 'entityNotActive',
  [CODES.ENTITY_REQUIRED]: 'entityRequired',
  [CODES.ENTITY_DELETED]: 'entityDeleted',
}

const { t } = useI18n()
const router = useRouter()
const handoff = useAssessmentResultHandoffStore()
const { state, draftId, publishedVersion, blockedCode, errorMessage, starting, locate, start } =
  useSelfAssessmentEntry()

const blockedKey = computed(() => BLOCKED_KEYS[blockedCode.value] ?? 'entityNotActive')

function onSubmitted(result) {
  handoff.hand(result)
  router.push({ name: ROUTE_NAMES.ASSESSMENT_SELF_RESULT, params: { id: result.id } })
}

onMounted(locate)
</script>

<template>
  <section class="self-form">
    <header class="self-form__header">
      <div>
        <p class="self-form__eyebrow">{{ t('assessment.self.eyebrow') }}</p>
        <h1 class="self-form__title">{{ t('assessment.self.title') }}</h1>
        <p class="self-form__subtitle">{{ t('assessment.self.subtitle') }}</p>
      </div>
    </header>

    <div class="self-form__note" role="note">
      <AppIcon name="info-circle" />
      <p>{{ t('assessment.self.unverifiedNote') }}</p>
    </div>

    <div v-if="state === ENTRY_STATES.LOADING" class="self-form__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="4" />
    </div>
    <ErrorState v-else-if="state === ENTRY_STATES.ERROR" :message="errorMessage" @retry="locate" />
    <EmptyState
      v-else-if="state === ENTRY_STATES.NO_CRITERIA"
      icon="doc"
      :title="t('assessment.self.empty.title')"
      :message="t('assessment.self.empty.message')"
    />
    <AssessmentNotice
      v-else-if="state === ENTRY_STATES.BLOCKED"
      variant="danger"
      live
      :title="t('assessment.self.blocked.title')"
      :message="t(`assessment.self.blocked.${blockedKey}`)"
    />
    <SelfStartCard
      v-else-if="state === ENTRY_STATES.START"
      :version="publishedVersion"
      :starting="starting"
      @start="start"
    />
    <SelfAssessmentWorkspace
      v-else-if="state === ENTRY_STATES.DRAFT"
      :key="draftId"
      :assessment-id="draftId"
      @submitted="onSubmitted"
    />
  </section>
</template>
