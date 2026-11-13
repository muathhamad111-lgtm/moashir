<script setup>
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import AccreditedFeedbackList from '@/components/accredited/AccreditedFeedbackList.vue'

defineProps({
  state: { type: Object, required: true },
})
</script>

<template>
  <div class="request-feedback" data-testid="request-tab-feedback">
    <Skeleton v-if="state.status.value === 'loading'" variant="text" :count="5" />
    <ErrorState
      v-else-if="state.status.value === 'error'"
      :message="state.errorMessage.value"
      @retry="state.load"
    />
    <AccreditedFeedbackList
      v-else-if="state.result.value?.available"
      :feedback="state.result.value.feedback ?? []"
    />
  </div>
</template>
