<script setup>
import { computed, nextTick, ref, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import AccreditedFormSummary from './AccreditedFormSummary.vue'
import AccreditedNotices from './AccreditedNotices.vue'
import AccreditedCompleteness from './AccreditedCompleteness.vue'
import AccreditedStations from './AccreditedStations.vue'
import AccreditedStationPanel from './AccreditedStationPanel.vue'
import CriterionStepButtons from '@/components/assessment/CriterionStepButtons.vue'
import AccreditedFormActions from './AccreditedFormActions.vue'
import AccreditedSubmitDialog from './AccreditedSubmitDialog.vue'
import { useAccreditedWorkspace } from '@/composables/useAccreditedWorkspace'
import { stationsOf, submissionProgressOf } from '@/utils/accredited/submissionProgress'

const props = defineProps({
  assessmentId: { type: Number, required: true },
})

const emit = defineEmits(['submitted'])
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
  revealSub,
  onRemove,
  refresh,
} = useAccreditedWorkspace({
  assessmentId: toRef(props, 'assessmentId'),
  onSubmitted: (data) => emit('submitted', data),
})
const { status, errorMessage, form, assessment, isEditable, isResubmission } = formState

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
</script>

<template>
  <div class="accredited-workspace">
    <div v-if="status === 'loading'" class="accredited-workspace__loading" aria-busy="true">
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
      <AccreditedFormSummary :assessment="assessment" :criteria="form.criteria" />
      <AccreditedNotices
        :assessment="assessment"
        :completion-request="form.completionRequest"
        :conflict-code="rejection.conflictCode.value"
        :refreshing="refreshing"
        @refresh="refresh"
      />
      <AccreditedCompleteness
        v-if="isEditable"
        :missing="form.missing"
        :criteria="form.criteria"
        @reveal="revealSub"
      />
      <AccreditedStations :stations="stations" :active-id="activeMain?.id ?? null" @select="goTo" />
      <div ref="panelTop" class="accredited-workspace__main" tabindex="-1">
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
        >
          <template #extra>
            <slot name="extra-actions" :assessment="assessment" />
          </template>
        </AccreditedFormActions>
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
    </template>
  </div>
</template>
