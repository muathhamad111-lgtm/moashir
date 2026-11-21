<script setup>
import { useI18n } from 'vue-i18n'
import FieldCheckbox from '@/components/form/FieldCheckbox.vue'
import RecordedStamp from '@/components/integrations/RecordedStamp.vue'

defineProps({
  prefix: { type: String, required: true },
  acknowledgement: { type: Object, default: null },
})

const { t } = useI18n()
</script>

<template>
  <div class="ai-acknowledgement">
    <FieldCheckbox
      :name="`${prefix}.dataTransferAcknowledged`"
      :label="t('integrations.ai.transfer.acknowledge')"
    />
    <RecordedStamp
      v-if="acknowledgement?.acknowledged"
      kind="acknowledged"
      :at="acknowledgement.acknowledgedAt"
      :by="acknowledgement.acknowledgedBy"
    />
  </div>
</template>
