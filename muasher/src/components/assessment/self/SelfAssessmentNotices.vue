<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import CriteriaRebindSummary from './CriteriaRebindSummary.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { SELF_ASSESSMENT_CODES as CODES } from '@/constants/assessment'

const props = defineProps({
  assessment: { type: Object, required: true },
  conflictCode: { type: String, default: '' },
  criteriaUpdate: { type: Object, default: null },
  refreshing: { type: Boolean, default: false },
})

const emit = defineEmits(['refresh'])
const { t } = useI18n()

const showCriteriaUpdate = computed(
  () => props.assessment.criteriaUpdateInProgress && !props.assessment.isFrozen,
)
const showConflict = computed(() => props.conflictCode === CODES.TRANSITION_ILLEGAL)
</script>

<template>
  <div class="self-notices">
    <AssessmentNotice
      v-if="assessment.isFrozen"
      variant="frozen"
      live
      :title="t('assessment.self.notices.frozenTitle')"
      :message="t('assessment.self.notices.frozenMessage')"
    />
    <AssessmentNotice
      v-if="showCriteriaUpdate"
      variant="warning"
      live
      :title="t('assessment.self.notices.criteriaUpdateTitle')"
      :message="t('assessment.self.notices.criteriaUpdateMessage')"
    >
      <template #action>
        <BaseButton
          variant="secondary"
          size="sm"
          icon="refresh"
          :loading="refreshing"
          @click="emit('refresh')"
        >
          {{ t('assessment.self.notices.checkAgain') }}
        </BaseButton>
      </template>
    </AssessmentNotice>
    <AssessmentNotice
      v-if="showConflict"
      variant="danger"
      live
      :title="t('assessment.self.notices.conflictTitle')"
      :message="t('assessment.self.notices.conflictMessage')"
    >
      <template #action>
        <BaseButton
          variant="secondary"
          size="sm"
          icon="refresh"
          :loading="refreshing"
          @click="emit('refresh')"
        >
          {{ t('assessment.self.notices.refresh') }}
        </BaseButton>
      </template>
    </AssessmentNotice>
    <CriteriaRebindSummary v-if="criteriaUpdate" :update="criteriaUpdate" />
  </div>
</template>
