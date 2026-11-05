<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import ScanStatusBadge from './ScanStatusBadge.vue'
import EvidenceUploadButton from './EvidenceUploadButton.vue'
import { SCAN_STATUSES } from '@/constants/accredited'
import { formatFileSize } from '@/utils/payments/amountFormat'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  file: { type: Object, required: true },
  editable: { type: Boolean, default: false },
  accept: { type: String, default: '' },
  busy: { type: Boolean, default: false },
  downloading: { type: Boolean, default: false },
})

const emit = defineEmits(['download', 'remove', 'replace'])
const { t, locale } = useI18n()

const size = computed(() =>
  t('accredited.evidence.sizeKb', { size: formatFileSize(props.file.sizeBytes, locale.value) }),
)
const isClean = computed(() => props.file.scanStatus === SCAN_STATUSES.CLEAN)
const isRefused = computed(() =>
  [SCAN_STATUSES.INFECTED, SCAN_STATUSES.FAILED].includes(props.file.scanStatus),
)
</script>

<template>
  <li class="evidence-file" :class="{ 'evidence-file--refused': isRefused }">
    <AppIcon name="doc" class="evidence-file__icon" />
    <span class="evidence-file__meta">
      <bdi class="evidence-file__name" dir="ltr">{{ file.fileName }}</bdi>
      <span class="evidence-file__size">{{ size }}</span>
    </span>
    <ScanStatusBadge :status="file.scanStatus" />
    <span class="evidence-file__actions">
      <BaseButton
        v-if="isClean"
        variant="ghost"
        size="sm"
        icon-only
        icon="download"
        :loading="downloading"
        :aria-label="t('accredited.evidence.download', { name: file.fileName })"
        @click="emit('download', file)"
      />
      <EvidenceUploadButton
        v-if="editable && isRefused"
        :label="t('accredited.evidence.replace')"
        icon="refresh"
        :accept="accept"
        :loading="busy"
        @select="emit('replace', { file, replacement: $event })"
      />
      <BaseButton
        v-if="editable"
        variant="ghost"
        size="sm"
        icon-only
        icon="trash"
        :disabled="busy"
        :aria-label="t('accredited.evidence.remove', { name: file.fileName })"
        @click="emit('remove', file)"
      />
    </span>
    <p v-if="isRefused && editable" class="evidence-file__refusal" role="alert">
      {{ t('accredited.evidence.refusedHint') }}
    </p>
  </li>
</template>
