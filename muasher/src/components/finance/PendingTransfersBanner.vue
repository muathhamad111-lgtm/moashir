<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

// Bank transfers wait for a decision: says how many, and filters the list to them.
const props = defineProps({
  count: { type: Number, required: true },
  // The list already shows only those transfers.
  showing: { type: Boolean, default: false },
})

const emit = defineEmits(['show'])
const { t } = useI18n()
</script>

<template>
  <div class="finance-pending" role="status" data-testid="finance-pending">
    <AppIcon name="landmark" class="finance-pending__icon" />
    <p class="finance-pending__text">
      {{ t('finance.page.pending.message', { count: props.count }, props.count) }}
    </p>
    <BaseButton v-if="!showing" variant="primary" size="sm" @click="emit('show')">
      {{ t('finance.page.pending.show') }}
    </BaseButton>
  </div>
</template>
