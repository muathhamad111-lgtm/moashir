<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import { ASSIGNMENT_MODES } from '@/constants/accredited'

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: (value) => Object.values(ASSIGNMENT_MODES).includes(value),
  },
  size: { type: String, default: 'lg' },
})

const emit = defineEmits(['assign'])
const { t } = useI18n()

const isFirstAssignment = computed(() => props.mode === ASSIGNMENT_MODES.ASSIGN)
</script>

<template>
  <BaseButton
    :variant="isFirstAssignment ? 'primary' : 'secondary'"
    :size="size"
    :icon="isFirstAssignment ? 'user-check' : 'pen'"
    class="request-assign-button"
    data-testid="request-assign"
    @click="emit('assign', mode)"
    >{{ t(`requests.actions.${mode}`) }}</BaseButton
  >
</template>
