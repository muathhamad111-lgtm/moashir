<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import CriteriaVersionBanner from './CriteriaVersionBanner.vue'
import CriteriaProblemList from './CriteriaProblemList.vue'
import CriteriaPublishDialog from './CriteriaPublishDialog.vue'
import CriteriaTextCorrectionDialog from './CriteriaTextCorrectionDialog.vue'
import CriteriaCycleBindings from './CriteriaCycleBindings.vue'
import { useCycleBindings } from '@/composables/useCycleBindings'
import { useCriteriaAccess } from '@/composables/useCriteriaAccess'
import { useConsistencyCheck } from '@/composables/usePublishCriteria'
import { useDraftLifecycle } from '@/composables/useDraftLifecycle'
import { toast } from '@/composables/useToast'
import { success } from '@/composables/useSuccess'
import { publishSummary } from '@/utils/criteriaPublishSummary'
import AppIcon from '@/components/ui/AppIcon.vue'

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
const cycles = useCycleBindings()

const publishOpen = ref(false)
const correctionOpen = ref(false)
const creating = ref(false)

function loadBindings() {
  if (access.isDraft.value) cycles.load(props.version.id)
}

watch(
  () => props.version.id,
  () => {
    consistency.clear()
    loadBindings()
  },
)

onMounted(loadBindings)

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
  success.show(publishSummary(t, outcome))
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
          icon="copy"
          :loading="creating"
          @click="startDraft"
        >
          {{ t('criteria.version.editPublished') }}
        </BaseButton>
        <BaseButton
          v-if="access.canCorrectText.value"
          variant="secondary"
          icon="pen-line"
          @click="correctionOpen = true"
        >
          {{ t('criteria.version.correctLabel') }}
        </BaseButton>
        <template v-if="access.isDraft.value">
          <BaseButton
            variant="secondary"
            icon="list-check"
            :loading="consistency.checking.value"
            @click="runCheck"
          >
            {{ t('criteria.version.check') }}
          </BaseButton>
          <BaseButton
            v-if="access.canPublishDraft.value"
            variant="primary"
            icon="upload"
            @click="publishOpen = true"
          >
            {{ t('criteria.version.publish') }}
          </BaseButton>
          <BaseButton v-if="access.canManage.value" variant="danger" icon="trash" @click="discard">
            {{ t('criteria.version.discardDraft') }}
          </BaseButton>
        </template>
      </template>
    </CriteriaVersionBanner>

    <CriteriaCycleBindings
      v-if="access.isDraft.value && cycles.isRelevant.value"
      :bindings="cycles.bindings.value"
    />

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
      <AppIcon name="check-circle" />
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
