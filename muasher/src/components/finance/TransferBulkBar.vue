<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import { BULK_DECISION_MAX } from '@/constants/bankTransfer'

defineProps({
  count: { type: Number, required: true },
  total: { type: Number, default: null },
  overLimit: { type: Boolean, default: false },
  clearable: { type: Boolean, default: true },
})

const emit = defineEmits(['approve', 'reject', 'clear'])
const { t } = useI18n()
</script>

<template>
  <div class="transfer-bulk-bar" role="region" :aria-label="t('finance.transfers.bulk.label')">
    <p class="transfer-bulk-bar__count" aria-live="polite">
      {{
        total === null
          ? t('finance.transfers.selection.count', { count })
          : t('finance.transfers.selection.countOf', { count, total })
      }}
    </p>
    <button v-if="clearable" type="button" class="transfer-bulk-bar__clear" @click="emit('clear')">
      {{ t('finance.transfers.selection.clear') }}
    </button>
    <p v-if="overLimit" class="transfer-bulk-bar__limit" role="alert">
      {{ t('finance.transfers.bulk.limit', { max: BULK_DECISION_MAX }) }}
    </p>
    <div class="transfer-bulk-bar__actions">
      <BaseButton variant="secondary" icon="x" :disabled="overLimit" @click="emit('reject')">
        {{ t('finance.transfers.bulk.reject', { count }) }}
      </BaseButton>
      <BaseButton variant="primary" icon="check" :disabled="overLimit" @click="emit('approve')">
        {{ t('finance.transfers.bulk.approve', { count }) }}
      </BaseButton>
    </div>
  </div>
</template>
