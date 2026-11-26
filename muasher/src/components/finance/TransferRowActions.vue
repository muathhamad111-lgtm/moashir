<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

// A queued transfer's actions: approve and reject for a reviewer on a decidable row, then review.
// The table shows icon buttons; the phone cards show them with their labels.
defineProps({
  item: { type: Object, required: true },
  decidable: { type: Boolean, default: false },
  labelled: { type: Boolean, default: false },
})

const emit = defineEmits(['view', 'approve', 'reject'])
const { t } = useI18n()
</script>

<template>
  <span class="transfer-queue__actions">
    <template v-if="decidable">
      <BaseButton
        variant="secondary"
        size="sm"
        icon="check"
        :icon-only="!labelled"
        class="transfer-queue__approve"
        :aria-label="t('finance.transfers.inline.approve', { reference: item.reference })"
        @click="emit('approve', item)"
        >{{ labelled ? t('finance.transfers.approve.open') : '' }}</BaseButton
      >
      <BaseButton
        variant="secondary"
        size="sm"
        icon="x"
        :icon-only="!labelled"
        class="transfer-queue__reject"
        :aria-label="t('finance.transfers.inline.reject', { reference: item.reference })"
        @click="emit('reject', item)"
        >{{ labelled ? t('finance.transfers.reject.open') : '' }}</BaseButton
      >
    </template>
    <BaseButton
      variant="secondary"
      size="sm"
      icon="eye"
      :icon-only="!labelled"
      class="transfer-queue__view"
      :aria-label="`${t('finance.transfers.review')} — ${item.reference}`"
      @click="emit('view', item)"
      >{{ labelled ? t('finance.transfers.review') : '' }}</BaseButton
    >
  </span>
</template>
