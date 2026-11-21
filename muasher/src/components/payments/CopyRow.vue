<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  label: { type: String, required: true },
  value: { type: String, required: true },
  display: { type: String, default: '' },
  latin: { type: Boolean, default: true },
  copied: { type: Boolean, default: false },
  wide: { type: Boolean, default: false },
})

const emit = defineEmits(['copy'])
const { t } = useI18n()
</script>

<template>
  <div class="copy-row" :class="{ 'copy-row--wide': wide, 'is-copied': copied }">
    <dt class="copy-row__label">{{ label }}</dt>
    <dd class="copy-row__body">
      <bdi v-if="latin" class="copy-row__value copy-row__value--latin" dir="ltr">{{
        display || value
      }}</bdi>
      <span v-else class="copy-row__value">{{ display || value }}</span>
      <BaseButton
        variant="secondary"
        size="sm"
        :icon="copied ? 'check' : 'copy'"
        :aria-label="t('portal.transfer.copy.label', { label })"
        @click="emit('copy')"
      >
        {{ copied ? t('portal.transfer.copy.copied') : t('portal.transfer.copy.action') }}
      </BaseButton>
    </dd>
  </div>
</template>
