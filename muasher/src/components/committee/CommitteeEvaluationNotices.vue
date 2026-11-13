<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import { ASSIGNMENT_STATUSES } from '@/constants/committee'
import { formatDate } from '@/utils/datetime'

const props = defineProps({
  evaluation: { type: Object, required: true },
  conflictCode: { type: String, default: '' },
})

const emit = defineEmits(['refresh'])
const { t, locale } = useI18n()
const submitted = computed(() => props.evaluation.status === ASSIGNMENT_STATUSES.COMPLETED)
</script>

<template>
  <div class="review-notices">
    <AssessmentNotice
      variant="info"
      :title="t('award.evaluation.blindTitle')"
      :message="t('award.evaluation.blindMessage')"
    />
    <AssessmentNotice
      v-if="submitted"
      variant="info"
      :title="t('award.evaluation.lockedTitle')"
      :message="t('award.evaluation.lockedMessage')"
    />
    <AssessmentNotice
      v-if="evaluation.returnNote"
      variant="warning"
      :title="
        t('award.evaluation.returnedTitle', {
          date: formatDate(evaluation.returnNote.returnedAt, locale, { dateStyle: 'medium' }),
        })
      "
      :message="evaluation.returnNote.note"
    />
    <AssessmentNotice
      v-if="evaluation.prior"
      variant="info"
      :title="t('award.evaluation.prior.noticeTitle')"
      :message="t('award.evaluation.prior.noticeMessage')"
    />
    <AssessmentNotice
      v-if="conflictCode"
      variant="danger"
      live
      :title="t('award.evaluation.conflictTitle')"
      :message="t('award.evaluation.conflictMessage')"
    >
      <template #action>
        <BaseButton variant="secondary" size="sm" icon="refresh" @click="emit('refresh')">{{
          t('award.evaluation.refresh')
        }}</BaseButton>
      </template>
    </AssessmentNotice>
  </div>
</template>
