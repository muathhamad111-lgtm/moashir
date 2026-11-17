<script setup>
import { computed, nextTick, ref, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import AccreditedRequestCard from './AccreditedRequestCard.vue'
import AccreditedNotices from './AccreditedNotices.vue'
import AccreditedStations from './AccreditedStations.vue'
import AccreditedStationPanel from './AccreditedStationPanel.vue'
import AccreditedFormActions from './AccreditedFormActions.vue'
import CriterionStepButtons from '@/components/assessment/CriterionStepButtons.vue'
import AccreditedSubmitDialog from './AccreditedSubmitDialog.vue'
import SessionExtendDialog from '@/components/session/SessionExtendDialog.vue'
import { useSessionExtensionPrompt } from '@/composables/useSessionExtensionPrompt'
import { useAccreditedWorkspace } from '@/composables/useAccreditedWorkspace'
import { stationsOf, submissionProgressOf } from '@/utils/accredited/submissionProgress'

const props = defineProps({
  assessmentId: { type: Number, required: true },
})

const emit = defineEmits(['submitted', 'loaded'])
const { t } = useI18n()
const panelTop = ref(null)

const {
  formState,
  rejection,
  cvDrafts,
  uploads,
  submitter,
  workspace,
  mains,
  activeMain,
  activeIndex,
  previousMain,
  nextMain,
  refreshing,
  selectMain,
  onRemove,
  refresh,
} = useAccreditedWorkspace({
  assessmentId: toRef(props, 'assessmentId'),
  onSubmitted: (data) => emit('submitted', data),
})
const { status, errorMessage, form, assessment, isEditable, isResubmission } = formState
const session = useSessionExtensionPrompt({
  saveBeforeSignOut: () =>
    isEditable.value && cvDrafts.dirty.value ? cvDrafts.save({ quiet: true }) : Promise.resolve(),
})

const stations = computed(() => stationsOf(form.value))
const progress = computed(() => submissionProgressOf(form.value))
const activeStation = computed(
  () => stations.value.find((station) => station.id === activeMain.value?.id) ?? null,
)

async function goTo(mainId) {
  selectMain(mainId)
  await nextTick()
  panelTop.value?.scrollIntoView({ block: 'start' })
  panelTop.value?.focus()
}

watch(assessment, (value) => {
  if (value) emit('loaded', value)
})
</script>

<template>
  <div class="accredited-task">
    <div v-if="status === 'loading'" class="accredited-task__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="6" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="formState.load" />
    <EmptyState
      v-else-if="status === 'missing'"
      icon="folder"
      :title="t('accredited.form.missing.title')"
      :message="t('accredited.form.missing.message')"
    />
    <EmptyState
      v-else-if="status === 'blocked'"
      icon="lock"
      :title="t('accredited.form.blocked.title')"
      :message="errorMessage || t('accredited.form.blocked.message')"
    />
    <template v-else-if="form">
      <AccreditedRequestCard
        :assessment="assessment"
        :progress="progress"
        :dirty="cvDrafts.dirty.value"
      />
      <AccreditedNotices
        :assessment="assessment"
        :completion-request="form.completionRequest"
        :conflict-code="rejection.conflictCode.value"
        :refreshing="refreshing"
        @refresh="refresh"
      />
      <AccreditedStations :stations="stations" :active-id="activeMain?.id ?? null" @select="goTo" />
      <div ref="panelTop" class="accredited-task__main" tabindex="-1">
        <AccreditedStationPanel
          v-if="activeMain && activeStation"
          :key="activeMain.id"
          :main="activeMain"
          :station="activeStation"
          :step="activeIndex + 1"
          :total="mains.length"
          :workspace="workspace"
          @upload="uploads.upload($event.sub.id, $event.key, $event.file)"
          @remove="onRemove"
          @replace="uploads.replace($event.file, $event.replacement)"
          @download="uploads.download"
        />
        <AccreditedFormActions
          v-if="isEditable"
          :saving="cvDrafts.saving.value"
          :submitting="submitter.submitting.value"
          :dirty="cvDrafts.dirty.value"
          :resubmission="isResubmission"
          :submit-blocked="assessment.criteriaUpdateInProgress"
          :remaining="progress.remaining"
          :is-first="!previousMain"
          :is-last="!nextMain"
          @save="cvDrafts.save()"
          @submit="submitter.requestSubmit"
          @previous="goTo(previousMain.id)"
          @next="goTo(nextMain.id)"
        />
        <CriterionStepButtons
          v-else
          :is-first="!previousMain"
          :is-last="!nextMain"
          @previous="goTo(previousMain.id)"
          @next="goTo(nextMain.id)"
        />
      </div>
      <AccreditedSubmitDialog
        v-model="submitter.confirmOpen.value"
        :number="assessment.number"
        :period="submitter.periodLabel.value"
        :blocked-until="submitter.blockedUntil.value"
        :resubmission="isResubmission"
        :submitting="submitter.submitting.value"
        @confirm="submitter.confirmSubmit"
      />
      <SessionExtendDialog
        :open="session.open.value"
        :countdown="session.countdown.value"
        :busy="session.busy.value"
        @extend="session.extend"
        @sign-out="session.signOut"
      />
    </template>
  </div>
</template>
