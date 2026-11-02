<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import CompletionRequestCard from './CompletionRequestCard.vue'
import { ACCREDITED_CODES as CODES, ENTITY_EDITABLE_STATUSES } from '@/constants/accredited'
import { AWARD_CODES } from '@/constants/advanced'
import { ASSESSMENT_PATHS } from '@/constants/assessment'

const props = defineProps({
  assessment: { type: Object, required: true },
  completionRequest: { type: Object, default: null },
  conflictCode: { type: String, default: '' },
  refreshing: { type: Boolean, default: false },
})

const emit = defineEmits(['refresh'])
const { t } = useI18n()

const isLockedStatus = computed(() => !ENTITY_EDITABLE_STATUSES.includes(props.assessment.status))
const CONFLICT_KEYS = {
  [CODES.DEADLINE_PASSED]: 'deadlinePassed',
  [CODES.ENTITY_DELETED]: 'frozen',
  [AWARD_CODES.SUBMISSION_CLOSED]: 'submissionClosed',
  [AWARD_CODES.CONDITIONS_FAILED]: 'conditionsFailed',
}
const ADVANCED_OVERRIDES = ['readOnly', 'completion', 'deadlinePassed']
const conflictKey = computed(() => CONFLICT_KEYS[props.conflictCode] ?? 'conflict')
const isAdvanced = computed(() => props.assessment.path === ASSESSMENT_PATHS.ADVANCED)

function noticeKey(name, part) {
  const root = isAdvanced.value && ADVANCED_OVERRIDES.includes(name) ? 'advanced.notices' : 'accredited.notices'
  return `${root}.${name}.${part}`
}
</script>

<template>
  <div class="accredited-notices">
    <AssessmentNotice v-if="assessment.isFrozen" variant="frozen" :title="t('accredited.notices.frozen.title')" :message="t('accredited.notices.frozen.message')" />
    <AssessmentNotice
      v-if="assessment.criteriaUpdateInProgress"
      variant="warning"
      :title="t('accredited.notices.criteriaUpdate.title')"
      :message="t('accredited.notices.criteriaUpdate.message')"
    />
    <AssessmentNotice v-if="conflictCode" variant="danger" live :title="t(noticeKey(conflictKey, 'title'))" :message="t(noticeKey(conflictKey, 'message'))">
      <template #action>
        <BaseButton variant="secondary" size="sm" icon="fa-solid fa-rotate" :loading="refreshing" @click="emit('refresh')">
          {{ t('accredited.notices.refresh') }}
        </BaseButton>
      </template>
    </AssessmentNotice>
    <AssessmentNotice
      v-if="isLockedStatus && !assessment.isFrozen"
      variant="info"
      :title="t(noticeKey('readOnly', 'title'))"
      :message="t(noticeKey('readOnly', 'message'))"
    />
    <AssessmentNotice v-if="completionRequest" variant="warning" :title="t(noticeKey('completion', 'title'))" :message="t(noticeKey('completion', 'message'))">
      <CompletionRequestCard :request="completionRequest" :title-key="isAdvanced ? 'advanced.completion.title' : 'accredited.completion.title'" />
    </AssessmentNotice>
  </div>
</template>
