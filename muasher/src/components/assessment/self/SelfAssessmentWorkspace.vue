<script setup>
import { computed, nextTick, onMounted, ref, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import CriteriaTree from '@/components/criteria/CriteriaTree.vue'
import SelfAssessmentSummary from './SelfAssessmentSummary.vue'
import SelfAssessmentNotices from './SelfAssessmentNotices.vue'
import SelfMainScoring from './SelfMainScoring.vue'
import SelfAssessmentActions from './SelfAssessmentActions.vue'
import SelfSubmitDialog from './SelfSubmitDialog.vue'
import { useSelfAssessmentForm } from '@/composables/useSelfAssessmentForm'
import { useSelfScoreSheet } from '@/composables/useSelfScoreSheet'
import { useSelfAssessmentActions } from '@/composables/useSelfAssessmentActions'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  assessmentId: { type: Number, required: true },
})

const emit = defineEmits(['submitted'])
const { t, locale } = useI18n()

const formState = useSelfAssessmentForm(toRef(props, 'assessmentId'))
const { status, errorMessage, form, assessment, units, isReadOnly, load } = formState
const scoreSheet = useSelfScoreSheet({ form, units })
const actions = useSelfAssessmentActions({
  formState,
  scoreSheet,
  onMissing: (missing) => revealMain(missing[0].mainId),
  onSubmitted: (result) => emit('submitted', result),
})
const { saving, submitting, confirmOpen, conflictCode } = actions
const activeMainId = ref(null)
const refreshing = ref(false)

const mains = computed(() => form.value?.criteria?.mains ?? [])
const activeMain = computed(
  () => mains.value.find((main) => main.id === activeMainId.value) ?? mains.value[0] ?? null,
)
const fieldsDisabled = computed(() => isReadOnly.value || saving.value || submitting.value)
const treeNodes = computed(() =>
  mains.value.map((main) => ({
    id: main.id,
    label: `${main.code} ${localizedText(main.name, locale.value)}`,
    meta: t('assessment.self.tree.mainMeta', { weight: main.weight }),
    children: main.subs.map((sub) => ({
      id: sub.id,
      label: `${sub.code} ${localizedText(sub.name, locale.value)}`,
    })),
  })),
)

watch(form, (current) => {
  if (!current) return
  scoreSheet.resetFrom(current.scores)
})

function revealMain(mainId) {
  activeMainId.value = mainId
  return nextTick()
}

function selectMain({ node, parent }) {
  revealMain(parent ? parent.id : node.id)
}

async function refresh() {
  refreshing.value = true
  actions.clearConflict()
  await load()
  refreshing.value = false
}

onMounted(load)
</script>

<template>
  <div class="self-workspace">
    <div v-if="status === 'loading' && !form" class="self-workspace__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="6" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
    <EmptyState
      v-else-if="status === 'missing'"
      icon="doc"
      :title="t('assessment.self.missing.title')"
      :message="t('assessment.self.missing.message')"
    />
    <template v-else-if="form">
      <SelfAssessmentSummary
        :assessment="assessment"
        :criteria="form.criteria"
        :scale="form.scale"
        :progress="form.progress"
      />
      <SelfAssessmentNotices
        :assessment="assessment"
        :conflict-code="conflictCode"
        :criteria-update="form.criteriaUpdate"
        :refreshing="refreshing"
        @refresh="refresh"
      />
      <div class="self-workspace__layout">
        <aside class="self-workspace__nav" :aria-label="t('assessment.self.nav.label')">
          <CriteriaTree
            :nodes="treeNodes"
            :active-id="activeMain?.id ?? null"
            @select="selectMain"
          />
        </aside>
        <SelfMainScoring
          v-if="activeMain"
          :key="activeMain.id"
          :main="activeMain"
          :scores="form.scores"
          :maximum="scoreSheet.maximum.value"
          :disabled="fieldsDisabled"
        />
      </div>
      <SelfAssessmentActions
        :saving="saving"
        :submitting="submitting"
        :read-only="isReadOnly"
        :submit-blocked="assessment.criteriaUpdateInProgress"
        :dirty="scoreSheet.dirty.value"
        @save="actions.save"
        @submit="actions.requestSubmit"
      />
      <SelfSubmitDialog
        v-model="confirmOpen"
        :number="assessment.number"
        :submitting="submitting"
        @confirm="actions.confirmSubmit"
      />
    </template>
  </div>
</template>
