<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseFileUpload from '@/components/form/BaseFileUpload.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Badge from '@/components/ui/Badge.vue'
import { REFUND_RULES, UPLOAD_STATES } from '@/constants/finance'
import { formatFileSize } from '@/utils/payments/amountFormat'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  file: { type: Object, default: null },
  uploadState: { type: String, required: true },
  refusal: { type: String, default: '' },
  error: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['select'])
const { t, locale } = useI18n()

const STATE_VARIANTS = { idle: 'muted', pending: 'warning', clean: 'success', refused: 'danger' }

const stateLabel = computed(() => t(`finance.refund.evidence.states.${props.uploadState}`))
const sizeLabel = computed(() =>
  props.file
    ? t('finance.common.sizeKb', { size: formatFileSize(props.file.size, locale.value) })
    : '',
)
const isPending = computed(() => props.uploadState === UPLOAD_STATES.PENDING)
</script>

<template>
  <div class="refund-evidence">
    <BaseFileUpload
      name="evidence"
      :label="t('finance.refund.evidence.label')"
      :accept="REFUND_RULES.EVIDENCE_ACCEPT"
      :hint="t('finance.refund.evidence.hint', { max: REFUND_RULES.EVIDENCE_MAX_MB })"
      :error="error"
      :disabled="disabled"
      required
      @select="emit('select', $event[0])"
    />
    <div v-if="file" class="refund-evidence__file" :aria-busy="isPending || undefined">
      <AppIcon name="doc" class="refund-evidence__icon" />
      <span class="refund-evidence__meta">
        <bdi class="refund-evidence__name" dir="ltr">{{ file.name }}</bdi>
        <span class="refund-evidence__size">{{ sizeLabel }}</span>
      </span>
      <Badge :variant="STATE_VARIANTS[uploadState]">
        <AppIcon v-if="isPending" name="refresh-dots" spin />
        {{ stateLabel }}
      </Badge>
      <BaseButton
        variant="ghost"
        size="sm"
        icon-only
        icon="trash"
        :disabled="disabled"
        :aria-label="t('finance.refund.evidence.remove', { name: file.name })"
        @click="emit('select', null)"
      />
    </div>
    <p v-if="refusal" class="refund-evidence__refusal" role="alert">
      <AppIcon name="shield-info" />
      {{ refusal }}
    </p>
    <p class="refund-evidence__status" aria-live="polite">{{ file ? stateLabel : '' }}</p>
  </div>
</template>
