<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import { formatDate } from '@/utils/datetime'

// The notices that call for attention: a return from the secretary, a carried-over evaluation,
// a save conflict. Independence and the lock read from the header's chips.
defineProps({
  evaluation: { type: Object, required: true },
  conflictCode: { type: String, default: '' },
})

const emit = defineEmits(['refresh'])
const { t, locale } = useI18n()
</script>

<template>
  <div class="review-notices">
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
