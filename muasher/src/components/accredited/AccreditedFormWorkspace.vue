<script setup>
import { computed, ref, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import CriteriaTree from '@/components/criteria/CriteriaTree.vue'
import AccreditedFormSummary from './AccreditedFormSummary.vue'
import AccreditedNotices from './AccreditedNotices.vue'
import AccreditedCompleteness from './AccreditedCompleteness.vue'
import AccreditedMainPanel from './AccreditedMainPanel.vue'
import AccreditedFormActions from './AccreditedFormActions.vue'
import AccreditedSubmitDialog from './AccreditedSubmitDialog.vue'
import { useAccreditedWorkspace } from '@/composables/useAccreditedWorkspace'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  assessmentId: { type: Number, required: true },
})

const emit = defineEmits(['submitted'])
const { t, locale } = useI18n()
const completeness = ref(null)

const {
  formState,
  rejection,
  cvDrafts,
  uploads,
  submitter,
  workspace,
  mains,
  activeMain,
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

const treeNodes = computed(() =>
  mains.value.map((main) => ({
    id: main.id,
    label: `${main.code} ${localizedText(main.name, locale.value)}`,
    children: main.subs.map((sub) => ({
      id: sub.id,
      label: `${sub.code} ${localizedText(sub.name, locale.value)}`,
    })),
  })),
)

function selectNode({ node, parent }) {
  if (parent) revealSub(node.id)
  else selectMain(node.id)
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
        ref="completeness"
        :missing="form.missing"
        :criteria="form.criteria"
        @reveal="revealSub"
      />
      <div class="accredited-workspace__layout">
        <aside class="accredited-workspace__nav" :aria-label="t('accredited.form.navLabel')">
          <CriteriaTree
            :nodes="treeNodes"
            :active-id="activeMain?.id ?? null"
            @select="selectNode"
          />
        </aside>
        <AccreditedMainPanel
          v-if="activeMain"
          :key="activeMain.id"
          :main="activeMain"
          :workspace="workspace"
          @upload="uploads.upload($event.sub.id, $event.key, $event.file)"
          @remove="onRemove"
          @replace="uploads.replace($event.file, $event.replacement)"
          @download="uploads.download"
        />
      </div>
      <AccreditedFormActions
        v-if="isEditable"
        :saving="cvDrafts.saving.value"
        :submitting="submitter.submitting.value"
        :dirty="cvDrafts.dirty.value"
        :resubmission="isResubmission"
        :submit-blocked="assessment.criteriaUpdateInProgress"
        @save="cvDrafts.save()"
        @submit="submitter.requestSubmit"
      >
        <template #extra>
          <slot name="extra-actions" :assessment="assessment" />
        </template>
      </AccreditedFormActions>
      <AccreditedSubmitDialog
        v-model="submitter.confirmOpen.value"
        :number="assessment.number"
        :resubmission="isResubmission"
        :submitting="submitter.submitting.value"
        @confirm="submitter.confirmSubmit"
      />
    </template>
  </div>
</template>
