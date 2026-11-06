<script setup>
import { computed, nextTick, onMounted, ref, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import MobileActionBar from '@/components/portal/MobileActionBar.vue'
import AccreditedRequestCard from './AccreditedRequestCard.vue'
import AccreditedNotices from './AccreditedNotices.vue'
import AccreditedStations from './AccreditedStations.vue'
import AccreditedStationPanel from './AccreditedStationPanel.vue'
import AccreditedGuideAside from './AccreditedGuideAside.vue'
import AccreditedStepNav from './AccreditedStepNav.vue'
import AccreditedCompleteness from './AccreditedCompleteness.vue'
import AccreditedFormActions from './AccreditedFormActions.vue'
import AccreditedSubmitDialog from './AccreditedSubmitDialog.vue'
import { useAccreditedWorkspace } from '@/composables/useAccreditedWorkspace'
import { usePathGuide } from '@/composables/usePathGuide'
import { stationsOf, submissionProgressOf } from '@/utils/accredited/submissionProgress'
import { ASSESSMENT_PATHS } from '@/constants/assessment'

const props = defineProps({
  assessmentId: { type: Number, required: true },
})

const emit = defineEmits(['submitted', 'loaded', 'contact'])
const { t } = useI18n()
const completeness = ref(null)
const panelTop = ref(null)
const guide = usePathGuide(ASSESSMENT_PATHS.ACCREDITED)

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
  refreshing,
  selectMain,
  revealSub,
  onRemove,
  refresh,
} = useAccreditedWorkspace({
  assessmentId: toRef(props, 'assessmentId'),
  onSubmitted: (data) => emit('submitted', data),
  completeness,
})
const { status, errorMessage, form, assessment, isEditable, isResubmission } = formState

const stations = computed(() => stationsOf(form.value))
const progress = computed(() => submissionProgressOf(form.value))
const activeStation = computed(
  () => stations.value.find((station) => station.id === activeMain.value?.id) ?? null,
)
const previousMain = computed(() => mains.value[activeIndex.value - 1] ?? null)
const nextMain = computed(() => mains.value[activeIndex.value + 1] ?? null)

async function goTo(mainId) {
  selectMain(mainId)
  await nextTick()
  panelTop.value?.scrollIntoView({ block: 'start' })
  panelTop.value?.focus()
}

watch(assessment, (value) => {
  if (value) emit('loaded', value)
})

onMounted(guide.load)
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
      >
        <template #actions>
          <AccreditedFormActions
            v-if="isEditable"
            class="accredited-task__actions"
            :saving="cvDrafts.saving.value"
            :submitting="submitter.submitting.value"
            :dirty="cvDrafts.dirty.value"
            :resubmission="isResubmission"
            :submit-blocked="assessment.criteriaUpdateInProgress"
            :remaining="progress.remaining"
            @save="cvDrafts.save()"
            @submit="submitter.requestSubmit"
          />
        </template>
      </AccreditedRequestCard>
      <AccreditedNotices
        :assessment="assessment"
        :completion-request="form.completionRequest"
        :conflict-code="rejection.conflictCode.value"
        :refreshing="refreshing"
        @refresh="refresh"
      />
      <AccreditedStations :stations="stations" :active-id="activeMain?.id ?? null" @select="goTo" />
      <div class="accredited-task__layout">
        <div ref="panelTop" class="accredited-task__main" tabindex="-1">
          <AccreditedStationPanel
            v-if="activeMain && activeStation"
            :key="activeMain.id"
            :main="activeMain"
            :station="activeStation"
            :workspace="workspace"
            @upload="uploads.upload($event.sub.id, $event.key, $event.file)"
            @remove="onRemove"
            @replace="uploads.replace($event.file, $event.replacement)"
            @download="uploads.download"
          />
          <AccreditedStepNav :previous="previousMain" :next="nextMain" @go="goTo" />
        </div>
        <aside class="accredited-task__aside" :aria-label="t('portal.assessments.task.asideLabel')">
          <AccreditedGuideAside :steps="guide.steps.value" @contact="emit('contact')">
            <AccreditedCompleteness
              v-if="isEditable"
              ref="completeness"
              :missing="form.missing"
              :criteria="form.criteria"
              @reveal="revealSub"
            />
          </AccreditedGuideAside>
        </aside>
      </div>
      <AccreditedSubmitDialog
        v-model="submitter.confirmOpen.value"
        :number="assessment.number"
        :resubmission="isResubmission"
        :submitting="submitter.submitting.value"
        @confirm="submitter.confirmSubmit"
      />
      <MobileActionBar v-if="isEditable" :label="t('accredited.form.actionsLabel')">
        <div class="accredited-task__bar">
          <BaseButton
            variant="secondary"
            :loading="cvDrafts.saving.value"
            :disabled="!cvDrafts.dirty.value"
            @click="cvDrafts.save()"
          >
            {{ t('portal.assessments.task.saveShort') }}
          </BaseButton>
          <BaseButton
            v-if="nextMain"
            variant="primary"
            icon-end="arrow-right"
            @click="goTo(nextMain.id)"
          >
            {{ t('portal.assessments.task.nextShort') }}
          </BaseButton>
          <BaseButton
            v-else
            variant="primary"
            :loading="submitter.submitting.value"
            @click="submitter.requestSubmit"
          >
            {{ isResubmission ? t('accredited.form.resubmit') : t('accredited.form.submit') }}
          </BaseButton>
        </div>
      </MobileActionBar>
    </template>
  </div>
</template>
