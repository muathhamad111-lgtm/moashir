<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import CriteriaVersionBanner from './CriteriaVersionBanner.vue'
import CriteriaProblemList from './CriteriaProblemList.vue'
import CriteriaPublishDialog from './CriteriaPublishDialog.vue'
import CriteriaTextCorrectionDialog from './CriteriaTextCorrectionDialog.vue'
import { useCriteriaAccess } from '@/composables/useCriteriaAccess'
import { useConsistencyCheck } from '@/composables/usePublishCriteria'
import { useDraftLifecycle } from '@/composables/useDraftLifecycle'
import { toast } from '@/composables/useToast'
import { publishSummary } from '@/utils/criteriaPublishSummary'

const props = defineProps({
  version: { type: Object, required: true },
  draft: { type: Object, default: null },
  published: { type: Object, default: null },
})

const emit = defineEmits(['changed'])
const { t } = useI18n()
const access = useCriteriaAccess(computed(() => props.version))
const consistency = useConsistencyCheck()
const lifecycle = useDraftLifecycle()

const publishOpen = ref(false)
const correctionOpen = ref(false)
const creating = ref(false)

watch(
  () => props.version.id,
  () => consistency.clear(),
)

const canStartDraft = computed(
  () => access.isFrozen.value && access.canManage.value && !props.draft,
)

async function startDraft() {
  creating.value = true
  const draft = await lifecycle.createDraft({ fromPublished: Boolean(props.published) })
  creating.value = false
  if (draft) emit('changed', { versionId: draft.id })
}

async function discard() {
  if (await lifecycle.discardDraft(props.version)) emit('changed', { versionId: null })
}

async function runCheck() {
  try {
    await consistency.check(props.version.id)
  } catch (error) {
    toast.error(error?.normalized?.message || t('criteria.toasts.loadFailed'))
  }
}

function onPublished(outcome) {
  toast.success(publishSummary(t, outcome), 9000)
  emit('changed', { versionId: outcome.data.id })
}

function onCorrected(outcome) {
  const count = outcome.meta?.changeCount ?? 0
  toast.success(
    count ? t('criteria.correction.success', { count }, count) : t('criteria.correction.noChanges'),
  )
  emit('changed', { versionId: props.version.id })
}
</script>

<template>
  <div class="criteria-version-panel">
    <CriteriaVersionBanner :version="version" :open-draft="draft">
      <template #actions>
        <BaseButton
          v-if="canStartDraft"
          variant="primary"
          icon="fa-solid fa-copy"
          :loading="creating"
          @click="startDraft"
        >
          {{ t('criteria.version.editPublished') }}
        </BaseButton>
        <BaseButton
          v-if="access.canCorrectText.value"
          variant="secondary"
          icon="fa-solid fa-spell-check"
          @click="correctionOpen = true"
        >
          {{ t('criteria.version.correctLabel') }}
        </BaseButton>
        <template v-if="access.isDraft.value">
          <BaseButton
            variant="secondary"
            icon="fa-solid fa-list-check"
            :loading="consistency.checking.value"
            @click="runCheck"
          >
            {{ t('criteria.version.check') }}
          </BaseButton>
          <BaseButton
            v-if="access.canPublishDraft.value"
            variant="primary"
            icon="fa-solid fa-upload"
            @click="publishOpen = true"
          >
            {{ t('criteria.version.publish') }}
          </BaseButton>
          <BaseButton
            v-if="access.canManage.value"
            variant="danger"
            icon="fa-solid fa-trash-can"
            @click="discard"
          >
            {{ t('criteria.version.discardDraft') }}
          </BaseButton>
        </template>
      </template>
    </CriteriaVersionBanner>

    <CriteriaProblemList
      v-if="consistency.problems.value.length"
      :problems="consistency.problems.value"
      :version-id="version.id"
    />
    <p
      v-else-if="consistency.checked.value"
      class="criteria-version-panel__consistent"
      role="status"
    >
      <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
      {{ t('criteria.version.consistent') }}
    </p>

    <CriteriaPublishDialog
      v-if="access.isDraft.value"
      v-model="publishOpen"
      :version="version"
      :published="published"
      @published="onPublished"
    />
    <CriteriaTextCorrectionDialog
      v-if="access.canCorrectText.value"
      v-model="correctionOpen"
      :version="version"
      @corrected="onCorrected"
    />
  </div>
</template>
