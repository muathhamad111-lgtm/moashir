<script setup>
import { onMounted, shallowRef } from 'vue'
import SelfResultBreakdown from '@/components/assessment/self/SelfResultBreakdown.vue'
import { assessmentsService } from '@/services/assessmentsService'
import { logger } from '@/utils/logger'

// The completed request's result per criterion, as bars with percents (design §B.3.4, AS-12): the
// same breakdown the self-assessment record shows. Without it the overview keeps its total card.
const props = defineProps({
  assessmentId: { type: Number, required: true },
})

const result = shallowRef(null)

onMounted(async () => {
  try {
    const { data } = await assessmentsService.getResult(props.assessmentId)
    result.value = data?.available ? data : null
  } catch (error) {
    logger.error(error, { action: 'requests.result.breakdown' })
  }
})
</script>

<template>
  <SelfResultBreakdown v-if="result" :result="result" />
</template>
