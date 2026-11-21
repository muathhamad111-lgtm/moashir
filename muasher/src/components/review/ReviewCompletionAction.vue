<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import { REVIEW_PAGE_ACTIONS_ID } from '@/constants/review'

// Every item is scored (and saved) before the entity is asked to complete anything (design §B.3.3,
// operator decision UX-1); until then the button says how many items are left.
defineProps({
  missing: { type: Number, default: 0 },
})

const emit = defineEmits(['open'])
const { t } = useI18n()
</script>

<!-- A request for completion concerns the whole assessment, so it sits in the page header. -->
<template>
  <Teleport defer :to="`#${REVIEW_PAGE_ACTIONS_ID}`">
    <BaseButton
      variant="primary"
      icon="reply"
      data-testid="review-request-completion"
      :disabled="missing > 0"
      @click="emit('open')"
      >{{
        missing > 0
          ? t('review.completion.scoreFirst', { count: missing }, missing)
          : t('review.requestCompletion')
      }}</BaseButton
    >
  </Teleport>
</template>
