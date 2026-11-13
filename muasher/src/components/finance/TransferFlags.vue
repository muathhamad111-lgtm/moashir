<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  transfer: { type: Object, required: true },
})

const { t } = useI18n()
const hasFlags = computed(
  () => props.transfer.hasDuplicateProof || props.transfer.isTest || props.transfer.entity.isFrozen,
)
</script>

<template>
  <span class="transfer-flags">
    <Badge v-if="transfer.hasDuplicateProof" variant="danger">
      <AppIcon name="copy" />
      {{ t('finance.transfers.flags.duplicate') }}
    </Badge>
    <Badge v-if="transfer.isTest" variant="warning">{{ t('finance.testMode.badge') }}</Badge>
    <Badge v-if="transfer.entity.isFrozen" variant="muted">
      <AppIcon name="lock" />
      {{ t('finance.transfers.flags.frozen') }}
    </Badge>
    <span v-if="!hasFlags" class="transfer-flags__none">{{ t('finance.common.none') }}</span>
  </span>
</template>
