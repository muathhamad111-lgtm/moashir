<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  label: { type: String, required: true },
  value: { type: String, required: true },
  copied: { type: Boolean, default: false },
  failed: { type: Boolean, default: false },
})

const emit = defineEmits(['copy'])
const { t } = useI18n()
</script>

<template>
  <div class="copy-value">
    <dt class="copy-value__label">{{ label }}</dt>
    <dd class="copy-value__body">
      <code class="copy-value__text" dir="ltr">{{ value }}</code>
      <BaseButton
        variant="ghost"
        size="sm"
        :icon="copied ? 'fa-solid fa-check' : 'fa-solid fa-copy'"
        :aria-label="t('integrations.copy.copyLabel', { label })"
        @click="emit('copy')"
      >
        {{ copied ? t('integrations.copy.copied') : t('integrations.copy.copy') }}
      </BaseButton>
      <span class="copy-value__status" role="status" aria-live="polite">
        <template v-if="copied">{{ t('integrations.copy.copiedLabel', { label }) }}</template>
        <template v-else-if="failed">{{ t('integrations.copy.failed') }}</template>
      </span>
    </dd>
  </div>
</template>
