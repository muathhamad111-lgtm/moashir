<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import { REVIEW_PAGE_ACTIONS_ID } from '@/constants/review'

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
      @click="emit('open')"
      >{{ t('review.requestCompletion') }}</BaseButton
    >
  </Teleport>
</template>
