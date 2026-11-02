<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import CriteriaScopeChoice from './CriteriaScopeChoice.vue'
import CriteriaProblemList from './CriteriaProblemList.vue'
import { usePublishCriteria } from '@/composables/usePublishCriteria'
import { useAuthStore } from '@/stores/auth'
import { logger } from '@/utils/logger'
import { CRITERIA_REASON_MIN_LENGTH } from '@/constants/criteria'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  version: { type: Object, required: true },
  published: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'published'])
const { t } = useI18n()
const auth = useAuthStore()
const { submitting, problems, fieldErrors, errorMessage, publish, reset } = usePublishCriteria()

const scope = ref(null)
const reason = ref('')
const localErrors = ref({})

const replacesPublished = computed(() => Boolean(props.published))
const reasonRequired = computed(() => replacesPublished.value && auth.isSuperAdmin)
const scopeError = computed(() => localErrors.value.scope ?? fieldErrors.value.scope?.[0] ?? '')
const reasonError = computed(() => localErrors.value.reason ?? fieldErrors.value.reason?.[0] ?? '')

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    scope.value = null
    reason.value = ''
    localErrors.value = {}
    reset()
  },
)

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
  logger.breadcrumb('form', 'criteria.publishDialog.submit', {
    scope: scope.value,
    hasReason: Boolean(reason.value),
  })
  const outcome = await publish(props.version, {
    scope: replacesPublished.value ? scope.value : null,
    reason: reason.value.trim() || null,
  })
  if (!outcome) return
  emit('published', outcome)
  emit('update:modelValue', false)
}

function close() {
  emit('update:modelValue', false)
}
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="lg"
    :close-on-backdrop="!submitting"
    :title="t('criteria.publish.dialogTitle', { number: version.versionNumber })"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <form
      class="publish-dialog"
      :aria-busy="submitting || undefined"
      novalidate
      @submit.prevent="submit"
    >
      <p class="publish-dialog__intro">
        {{ t('criteria.publish.intro', { number: version.versionNumber }) }}
      </p>
      <p class="publish-dialog__note">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        <span v-if="replacesPublished">{{
          t('criteria.publish.replacesNote', { number: published.versionNumber })
        }}</span>
        <span v-else>{{ t('criteria.publish.firstNote') }}</span>
      </p>

      <CriteriaScopeChoice
        v-if="replacesPublished"
        v-model="scope"
        :error="scopeError"
        :disabled="submitting"
      />

      <BaseTextarea
        v-if="replacesPublished"
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

      <CriteriaProblemList
        v-if="problems.length"
        :problems="problems"
        :version-id="version.id"
        :title="t('criteria.publish.blockedTitle')"
        @navigate="close"
      />
      <p v-else-if="errorMessage" class="publish-dialog__error" role="alert">{{ errorMessage }}</p>

      <footer class="modal__footer">
        <BaseButton variant="secondary" :disabled="submitting" @click="close">{{
          t('actions.cancel')
        }}</BaseButton>
        <BaseButton type="submit" variant="primary" icon="fa-solid fa-upload" :loading="submitting">
          {{ t('criteria.publish.submit', { number: version.versionNumber }) }}
        </BaseButton>
      </footer>
    </form>
  </BaseModal>
</template>
