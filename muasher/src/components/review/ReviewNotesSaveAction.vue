<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import { REVIEW_PAGE_ACTIONS_ID } from '@/constants/review'

// Staff who read the review without scoring it save their internal notes from the page header; the
// assessor's «حفظ» saves them with the scores.
defineProps({
  dirty: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['save'])
const { t } = useI18n()
</script>

<template>
  <Teleport defer :to="`#${REVIEW_PAGE_ACTIONS_ID}`">
    <BaseButton
      variant="primary"
      icon="lock"
      data-testid="review-notes-save"
      :disabled="!dirty"
      :loading="busy"
      @click="emit('save')"
      >{{ t('review.internalNotes.save') }}</BaseButton
    >
  </Teleport>
</template>
