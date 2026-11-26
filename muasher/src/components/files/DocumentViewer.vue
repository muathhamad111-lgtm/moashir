<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { PREVIEW_STATES, useDocumentPreview } from '@/composables/useDocumentPreview'
import { useMediaQuery } from '@/composables/useMediaQuery'
import { DESKTOP_QUERY } from '@/constants/breakpoints'
import { SCAN_STATUSES } from '@/constants/finance'
import { formatFileSize } from '@/utils/payments/amountFormat'
import { isImageProof, isPdfProof } from '@/utils/payments/proofFile'

const props = defineProps({
  file: { type: Object, required: true },
  label: { type: String, required: true },
  requestLink: { type: Function, required: true },
})

const { t, locale } = useI18n()
const isDesktop = useMediaQuery(DESKTOP_QUERY)
const preview = useDocumentPreview(props.requestLink)

const scanStatus = computed(() => props.file.scanStatus ?? SCAN_STATUSES.CLEAN)
const isClean = computed(() => scanStatus.value === SCAN_STATUSES.CLEAN)
const isImage = computed(() => isImageProof(props.file))
const isPdf = computed(() => isPdfProof(props.file))
const previewable = computed(
  () => isClean.value && (isImage.value || (isPdf.value && isDesktop.value)),
)
const ready = computed(
  () => preview.state.value === PREVIEW_STATES.READY && preview.objectUrl.value,
)
const sizeLabel = computed(() =>
  t('finance.common.sizeKb', { size: formatFileSize(props.file.sizeBytes, locale.value) }),
)
const typeLabel = computed(() => String(props.file.name).split('.').pop().toUpperCase())

onMounted(() => {
  if (previewable.value) preview.load()
})
</script>

<template>
  <section class="document-viewer" :aria-label="label">
    <header class="document-viewer__head">
      <span class="document-viewer__icon" aria-hidden="true"><AppIcon name="doc" /></span>
      <span class="document-viewer__meta">
        <bdi class="document-viewer__name" dir="ltr">{{ file.name }}</bdi>
        <span class="document-viewer__size">
          {{ sizeLabel }} · {{ t(`components.documentViewer.scan.${scanStatus}`) }} ·
          <bdi dir="ltr">{{ typeLabel }}</bdi>
        </span>
      </span>
      <div v-if="isImage && ready" class="document-viewer__zoom">
        <BaseButton
          variant="secondary"
          size="sm"
          icon-only
          icon="minus"
          :disabled="!preview.canZoomOut.value"
          :aria-label="t('components.documentViewer.zoomOut')"
          @click="preview.zoomOut"
        />
        <span class="document-viewer__zoom-value" aria-live="polite">
          <bdi dir="ltr">{{ preview.zoom.value }}%</bdi>
        </span>
        <BaseButton
          variant="secondary"
          size="sm"
          icon-only
          icon="plus"
          :disabled="!preview.canZoomIn.value"
          :aria-label="t('components.documentViewer.zoomIn')"
          @click="preview.zoomIn"
        />
      </div>
      <BaseButton
        v-if="isClean"
        variant="secondary"
        icon="download"
        :loading="preview.downloading.value"
        @click="preview.download"
      >
        {{ t('components.documentViewer.open') }}
      </BaseButton>
    </header>

    <div class="document-viewer__stage">
      <p v-if="!isClean" class="document-viewer__placeholder">
        <AppIcon name="refresh-dots" />
        {{ t('components.documentViewer.notScanned') }}
      </p>
      <p v-else-if="!previewable" class="document-viewer__placeholder">
        <AppIcon name="doc" />
        {{ t('components.documentViewer.pdfOnDesktop') }}
      </p>
      <Skeleton v-else-if="preview.state.value === PREVIEW_STATES.LOADING" variant="block" />
      <p
        v-else-if="preview.state.value === PREVIEW_STATES.ERROR"
        class="document-viewer__placeholder"
        role="alert"
      >
        <AppIcon name="warning-triangle" />
        {{ t('components.documentViewer.failed') }}
        <BaseButton variant="ghost" size="sm" icon="refresh" @click="preview.load">
          {{ t('components.documentViewer.retry') }}
        </BaseButton>
      </p>
      <div v-else-if="ready && isImage" class="document-viewer__image-frame">
        <img
          class="document-viewer__image"
          :class="`document-viewer__image--zoom-${preview.zoom.value}`"
          :src="preview.objectUrl.value"
          :alt="t('components.documentViewer.imageAlt', { label, name: file.name })"
        />
      </div>
      <iframe
        v-else-if="ready && isPdf"
        class="document-viewer__pdf"
        :src="preview.objectUrl.value"
        :title="t('components.documentViewer.pdfTitle', { label, name: file.name })"
      ></iframe>
    </div>

    <p class="document-viewer__logged">
      <AppIcon name="shield-info" />
      {{ t('components.documentViewer.logged') }}
    </p>
  </section>
</template>
