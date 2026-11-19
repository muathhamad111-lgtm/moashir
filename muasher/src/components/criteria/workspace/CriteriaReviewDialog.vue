<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import CriteriaProblemList from '@/components/criteria/CriteriaProblemList.vue'
import CriteriaPublishPreview from '@/components/criteria/CriteriaPublishPreview.vue'
import CriteriaReviewApply from './CriteriaReviewApply.vue'
import { useConsistencyCheck } from '@/composables/usePublishCriteria'
import { usePublicationPreview } from '@/composables/usePublicationPreview'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  version: { type: Object, required: true },
  published: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'published'])
const { t } = useI18n()
const consistency = useConsistencyCheck()
const preview = usePublicationPreview()

const replacesPublished = computed(() => Boolean(props.published))
const steps = computed(() =>
  replacesPublished.value ? ['notes', 'changes', 'apply'] : ['notes', 'apply'],
)
const step = ref('notes')
const stepIndex = computed(() => steps.value.indexOf(step.value))
const hasProblems = computed(() => consistency.problems.value.length > 0)

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    step.value = 'notes'
    consistency.check(props.version.id).catch(() => null)
    if (replacesPublished.value) preview.load(props.version.id)
  },
)

function go(offset) {
  step.value = steps.value[stepIndex.value + offset] ?? step.value
}

function close() {
  emit('update:modelValue', false)
}

function onPublished(outcome) {
  emit('published', outcome)
  close()
}
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="lg"
    :title="t('criteria.workspace.review.title', { number: version.versionNumber })"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <ol class="criteria-review__steps" :aria-label="t('criteria.workspace.review.stepsLabel')">
      <li
        v-for="(name, position) in steps"
        :key="name"
        class="criteria-review__step"
        :class="{ 'is-current': name === step }"
        :aria-current="name === step ? 'step' : undefined"
      >
        {{ position + 1 }}. {{ t(`criteria.workspace.review.steps.${name}`) }}
      </li>
    </ol>

    <section v-if="step === 'notes'" class="publish-dialog" :aria-busy="consistency.checking.value">
      <p v-if="consistency.checking.value" class="criteria-details__note">
        {{ t('criteria.workspace.review.checking') }}
      </p>
      <CriteriaProblemList
        v-else-if="hasProblems"
        :problems="consistency.problems.value"
        :version-id="version.id"
        :title="t('criteria.publish.blockedTitle')"
        @navigate="close"
      />
      <p v-else class="criteria-review__ready" role="status">
        <AppIcon name="check-circle" />
        {{ t('criteria.workspace.review.ready') }}
      </p>
      <footer class="modal__footer">
        <BaseButton
          v-if="!hasProblems && !consistency.checking.value"
          variant="primary"
          @click="go(1)"
        >
          {{ t('criteria.workspace.review.next') }}
        </BaseButton>
        <BaseButton variant="secondary" @click="close">{{ t('actions.cancel') }}</BaseButton>
      </footer>
    </section>

    <section v-else-if="step === 'changes'" class="publish-dialog">
      <CriteriaPublishPreview
        part="changes"
        :preview="preview.preview.value"
        :loading="preview.loading.value"
        :failed="preview.failed.value"
        @retry="preview.load(version.id)"
      />
      <footer class="modal__footer">
        <BaseButton variant="primary" @click="go(1)">{{
          t('criteria.workspace.review.next')
        }}</BaseButton>
        <BaseButton variant="secondary" @click="go(-1)">{{
          t('criteria.workspace.review.back')
        }}</BaseButton>
      </footer>
    </section>

    <CriteriaReviewApply
      v-else
      :version="version"
      :published="published"
      :preview="preview"
      @back="go(-1)"
      @published="onPublished"
      @navigate="close"
    />
  </BaseModal>
</template>
