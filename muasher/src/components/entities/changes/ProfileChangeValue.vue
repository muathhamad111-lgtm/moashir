<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useProfileChangeFormat } from '@/composables/useProfileChangeFormat'

const props = defineProps({
  change: { type: Object, required: true },
  side: { type: String, required: true, validator: (value) => ['old', 'new'].includes(value) },
})

const { t } = useI18n()
const { valueLabel } = useProfileChangeFormat()

const value = computed(() => (props.side === 'old' ? props.change.oldValue : props.change.newValue))
const isFile = computed(() => props.change.kind === 'file')
const removesFile = computed(() => isFile.value && props.side === 'new' && props.change.removes)
</script>

<template>
  <span v-if="removesFile" class="change-value change-value--removed">
    {{ t('entities.profileChanges.review.removesFile') }}
  </span>
  <span v-else-if="isFile && !value" class="change-value change-value--empty">
    {{ t('entities.profileChanges.review.noFile') }}
  </span>
  <span v-else-if="isFile" class="change-value">
    <a v-if="value.url" :href="value.url" target="_blank" rel="noopener" class="change-value__file">
      {{ value.name || t('entities.profileChanges.review.openFile') }}
    </a>
    <span v-else class="change-value--empty">{{
      t('entities.profileChanges.review.scanPending')
    }}</span>
  </span>
  <bdi v-else class="change-value" dir="auto">{{ valueLabel(change.item, value) }}</bdi>
</template>
