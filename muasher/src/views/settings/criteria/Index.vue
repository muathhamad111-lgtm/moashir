<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import CriteriaWorkspaceBar from '@/components/criteria/workspace/CriteriaWorkspaceBar.vue'
import CriteriaTree from '@/components/criteria/workspace/CriteriaTree.vue'
import CriteriaNodeDetails from '@/components/criteria/workspace/CriteriaNodeDetails.vue'
import CriteriaVersionOverview from '@/components/criteria/workspace/CriteriaVersionOverview.vue'
import CriteriaReviewDialog from '@/components/criteria/workspace/CriteriaReviewDialog.vue'
import CriteriaCreateDialogs from '@/components/criteria/workspace/CriteriaCreateDialogs.vue'
import CriteriaDraftDialog from '@/components/criteria/CriteriaDraftDialog.vue'
import CriteriaTextCorrectionDialog from '@/components/criteria/CriteriaTextCorrectionDialog.vue'
import { useCriteriaWorkspace } from '@/composables/useCriteriaWorkspace'
import { useCriteriaWorkspaceEditing } from '@/composables/useCriteriaWorkspaceEditing'
import { useCriteriaAccess } from '@/composables/useCriteriaAccess'
import { useDraftLifecycle } from '@/composables/useDraftLifecycle'
import { success } from '@/composables/useSuccess'
import { publishSummary } from '@/utils/criteriaPublishSummary'

const { t } = useI18n()
const workspace = useCriteriaWorkspace()
const editing = useCriteriaWorkspaceEditing(workspace)
const lifecycle = useDraftLifecycle()
const access = useCriteriaAccess(workspace.version)
const { versions } = workspace

const reviewOpen = ref(false)
const cloneOpen = ref(false)
const canEdit = computed(() => access.canEditTree.value)

onMounted(workspace.load)

async function afterVersionChange() {
  await workspace.showVersion(null)
  await workspace.reload()
}

async function startEditing() {
  if (await lifecycle.createDraft({ fromPublished: Boolean(versions.published.value) })) {
    await afterVersionChange()
  }
}

async function cloneDraft(payload) {
  await lifecycle.cloneDraft(payload)
  await afterVersionChange()
}

async function discard() {
  if (await lifecycle.discardDraft(workspace.version.value)) await afterVersionChange()
}

async function onPublished(outcome) {
  success.show(publishSummary(t, outcome))
  await afterVersionChange()
}
</script>

<template>
  <section class="criteria-workspace">
    <header class="criteria-page__header">
      <div>
        <h1 class="criteria-page__title">{{ t('criteria.index.title') }}</h1>
        <p class="criteria-page__subtitle">{{ t('criteria.workspace.subtitle') }}</p>
      </div>
    </header>

    <div
      v-if="workspace.status.value === 'loading' && !workspace.tree.value"
      class="criteria-page__loading"
    >
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="6" />
    </div>
    <EmptyState
      v-else-if="workspace.status.value === 'empty'"
      :title="t('criteria.index.noVersionsTitle')"
      :message="t('criteria.index.noVersionsMessage')"
      icon="hierarchy"
    >
      <template v-if="access.canManage.value" #action>
        <BaseButton variant="primary" icon="plus" @click="startEditing">
          {{ t('criteria.version.createEmptyDraft') }}
        </BaseButton>
      </template>
    </EmptyState>
    <ErrorState
      v-else-if="workspace.status.value === 'error'"
      :message="workspace.errorMessage.value"
      @retry="workspace.reload"
    />

    <template v-else-if="workspace.version.value && workspace.tree.value">
      <CriteriaWorkspaceBar
        :version="workspace.version.value"
        :draft="versions.draft.value"
        :versions="versions.versions.value"
        :problem-count="workspace.problems.value.length"
        :can-manage="access.canManage.value"
        :can-publish="access.canPublish.value"
        @start="startEditing"
        @review="reviewOpen = true"
        @discard="discard"
        @clone="cloneOpen = true"
        @correct-name="editing.correctVersionName"
        @show-version="workspace.showVersion"
      />

      <div class="criteria-workspace__layout">
        <CriteriaTree
          :index="workspace.index.value"
          :selected-key="workspace.selected.value?.key ?? null"
          :can-edit="canEdit"
          @select="workspace.select"
          @add="editing.startCreate"
          @reorder="editing.reorder"
        />
        <CriteriaNodeDetails
          v-if="workspace.selected.value"
          :key="workspace.selected.value.key"
          :item="workspace.selected.value"
          :index="workspace.index.value"
          :path="workspace.selectedPath.value"
          :can-edit="canEdit"
          :can-correct="access.canCorrectText.value"
          :save-node="editing.saveSelected"
          :reload="workspace.reload"
          @select="workspace.select"
          @add="editing.startCreate"
          @toggle="editing.toggleSelected"
          @remove="editing.removeSelected"
          @correct="editing.correctSelected"
        />
        <CriteriaVersionOverview
          v-else
          :tree="workspace.tree.value"
          :index="workspace.index.value"
          :can-edit="canEdit"
          @add="editing.startCreate(null)"
        />
      </div>

      <CriteriaCreateDialogs v-if="canEdit" :editing="editing" />
      <CriteriaReviewDialog
        v-if="access.canPublishDraft.value"
        v-model="reviewOpen"
        :version="workspace.version.value"
        :published="versions.published.value"
        @published="onPublished"
      />
      <CriteriaDraftDialog
        v-if="access.canManage.value && !versions.draft.value"
        v-model="cloneOpen"
        :versions="versions.versions.value"
        :source-id="workspace.version.value.id"
        :create="cloneDraft"
      />
      <CriteriaTextCorrectionDialog
        v-if="access.canCorrectText.value"
        :model-value="editing.correction.value.open"
        :version="workspace.version.value"
        :target="editing.correction.value.target"
        @update:model-value="editing.closeCorrection"
        @corrected="editing.corrected"
      />
    </template>
  </section>
</template>
