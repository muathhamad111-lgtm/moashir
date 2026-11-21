<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import CriteriaScopeChoice from '@/components/criteria/CriteriaScopeChoice.vue'
import CriteriaProblemList from '@/components/criteria/CriteriaProblemList.vue'
import CriteriaCycleBindings from '@/components/criteria/CriteriaCycleBindings.vue'
import { usePublishCriteria } from '@/composables/usePublishCriteria'
import { useCycleBindings } from '@/composables/useCycleBindings'
import { useAuthStore } from '@/stores/auth'
import { logger } from '@/utils/logger'
import { CRITERIA_REASON_MIN_LENGTH } from '@/constants/criteria'

const props = defineProps({
  version: { type: Object, required: true },
  published: { type: Object, default: null },
  preview: { type: Object, required: true },
})

const emit = defineEmits(['back', 'published', 'navigate'])
const { t } = useI18n()
const auth = useAuthStore()
const { submitting, problems, fieldErrors, errorMessage, publish } = usePublishCriteria()
const cycles = useCycleBindings()

const scope = ref(null)
const reason = ref('')
const localErrors = ref({})

const replacesPublished = computed(() => Boolean(props.published))
const reasonRequired = computed(() => replacesPublished.value && auth.isSuperAdmin)
const scopeError = computed(() => localErrors.value.scope ?? fieldErrors.value.scope?.[0] ?? '')
const reasonError = computed(() => localErrors.value.reason ?? fieldErrors.value.reason?.[0] ?? '')

onMounted(() => cycles.load(props.version.id))

function validate() {
  const errors = {}
  const trimmed = reason.value.trim()
  if (replacesPublished.value && !scope.value) errors.scope = t('criteria.publish.scopeRequired')
  if (reasonRequired.value && !trimmed) errors.reason = t('criteria.publish.reasonRequired')
  if (trimmed && trimmed.length < CRITERIA_REASON_MIN_LENGTH) {
    errors.reason = t('criteria.validation.reasonMin', { min: CRITERIA_REASON_MIN_LENGTH })
  }
  localErrors.value = errors
  return !Object.keys(errors).length
}

async function submit() {
  if (!validate()) return
  logger.breadcrumb('form', 'criteria.reviewDialog.submit', {
    scope: scope.value,
    hasReason: Boolean(reason.value),
    draftCycles: cycles.instruction().draftCycles ?? null,
  })
  const outcome = await publish(props.version, {
    scope: replacesPublished.value ? scope.value : null,
    reason: reason.value.trim() || null,
    ...cycles.instruction(),
  })
  if (outcome) emit('published', outcome)
}
</script>

<template>
  <form
    class="criteria-review-page__section publish-dialog"
    :aria-busy="submitting || undefined"
    novalidate
    @submit.prevent="submit"
  >
    <CriteriaCycleBindings
      v-if="cycles.isRelevant.value"
      v-model:choice="cycles.choice.value"
      :bindings="cycles.bindings.value"
      :disabled="submitting"
      show-choice
    />
    <template v-if="replacesPublished">
      <CriteriaScopeChoice
        v-model="scope"
        :error="scopeError"
        :disabled="submitting"
        :counts="preview.preview.value?.reboundOpenAssessments ?? null"
      />
      <BaseTextarea
        v-model="reason"
        name="publishReason"
        :rows="3"
        :label="t('criteria.publish.reason')"
        :hint="
          reasonRequired
            ? t('criteria.publish.reasonHintRequired')
            : t('criteria.publish.reasonHint')
        "
        :error="reasonError"
        :required="reasonRequired"
        :disabled="submitting"
      />
    </template>
    <p v-else class="criteria-details__note">{{ t('criteria.publish.firstNote') }}</p>

    <CriteriaProblemList
      v-if="problems.length"
      :problems="problems"
      :version-id="version.id"
      :title="t('criteria.publish.blockedTitle')"
      @navigate="emit('navigate')"
    />
    <p v-else-if="errorMessage" class="publish-dialog__error" role="alert">{{ errorMessage }}</p>

    <footer class="criteria-review-page__bar">
      <BaseButton
        variant="secondary"
        icon="arrow-left"
        :disabled="submitting"
        @click="emit('back')"
      >
        {{ t('criteria.workspace.review.back') }}
      </BaseButton>
      <BaseButton type="submit" variant="primary" icon="upload" :loading="submitting">
        {{ t('criteria.review.publish', { number: version.versionNumber }) }}
      </BaseButton>
    </footer>
  </form>
</template>
