<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { QUEUE_STAGES } from '@/constants/accredited'
import { stageLabelKey } from '@/utils/assessments/queueStages'

// The request list's quick stage filters, each with its count when the counts are known.
const props = defineProps({
  modelValue: { type: String, required: true },
  counts: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

const chips = computed(() =>
  Object.values(QUEUE_STAGES).map((stage) => {
    const label = t(stageLabelKey(stage))
    const count = props.counts?.[stage]
    return {
      stage,
      label: count === undefined ? label : t('requests.stages.withCount', { label, count }),
    }
  }),
)
</script>

<template>
  <div class="queue-stages" role="group" :aria-label="t('requests.stages.label')">
    <button
      v-for="chip in chips"
      :key="chip.stage"
      type="button"
      class="queue-stages__chip"
      :class="{ 'is-active': chip.stage === modelValue }"
      :aria-pressed="String(chip.stage === modelValue)"
      :data-stage="chip.stage"
      @click="emit('update:modelValue', chip.stage)"
    >
      {{ chip.label }}
    </button>
  </div>
</template>
