<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { PREVIEW_STATES, useProofPreview } from '@/composables/useProofPreview'
import { useMediaQuery } from '@/composables/useMediaQuery'
import { DESKTOP_QUERY } from '@/constants/breakpoints'
import { SCAN_STATUSES } from '@/constants/finance'
import { formatFileSize } from '@/utils/payments/amountFormat'
import { isImageProof, isPdfProof } from '@/utils/payments/proofFile'

const props = defineProps({
  proof: { type: Object, required: true },
  requestLink: { type: Function, required: true },
})

const { t, locale } = useI18n()
const isDesktop = useMediaQuery(DESKTOP_QUERY)
const preview = useProofPreview(props.requestLink)

const isClean = computed(() => props.proof.scanStatus === SCAN_STATUSES.CLEAN)
const isImage = computed(() => isImageProof(props.proof))
const isPdf = computed(() => isPdfProof(props.proof))
const previewable = computed(
  () => isClean.value && (isImage.value || (isPdf.value && isDesktop.value)),
)
const ready = computed(
  () => preview.state.value === PREVIEW_STATES.READY && preview.objectUrl.value,
)
const sizeLabel = computed(() =>
  t('finance.common.sizeKb', { size: formatFileSize(props.proof.sizeBytes, locale.value) }),
)
const typeLabel = computed(() => String(props.proof.name).split('.').pop().toUpperCase())

onMounted(() => {
  if (previewable.value) preview.load()
})
</script>

<template>
  <section class="proof-viewer" :aria-label="t('finance.transfers.proof.title')">
    <header class="proof-viewer__head">
      <span class="proof-viewer__icon" aria-hidden="true"><AppIcon name="doc" /></span>
      <span class="proof-viewer__meta">
        <bdi class="proof-viewer__name" dir="ltr">{{ proof.name }}</bdi>
        <span class="proof-viewer__size">
          {{ sizeLabel }} ·
          {{ t(`finance.transfers.proof.scan.${proof.scanStatus ?? 'pending'}`) }} ·
          <bdi dir="ltr">{{ typeLabel }}</bdi>
        </span>
      </span>
      <div v-if="isImage && ready" class="proof-viewer__zoom">
        <BaseButton
          variant="secondary"
          size="sm"
          icon-only
          icon="minus"
          :disabled="!preview.canZoomOut.value"
          :aria-label="t('finance.transfers.proof.zoomOut')"
          @click="preview.zoomOut"
        />
        <span class="proof-viewer__zoom-value" aria-live="polite">
          <bdi dir="ltr">{{ preview.zoom.value }}%</bdi>
        </span>
        <BaseButton
          variant="secondary"
          size="sm"
          icon-only
          icon="plus"
          :disabled="!preview.canZoomIn.value"
          :aria-label="t('finance.transfers.proof.zoomIn')"
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
        {{ t('finance.transfers.proof.open') }}
      </BaseButton>
    </header>

    <div class="proof-viewer__stage">
      <p v-if="!isClean" class="proof-viewer__placeholder">
        <AppIcon name="refresh-dots" />
        {{ t('finance.transfers.proof.notScanned') }}
      </p>
      <p v-else-if="!previewable" class="proof-viewer__placeholder">
        <AppIcon name="doc" />
        {{ t('finance.transfers.proof.pdfOnDesktop') }}
      </p>
      <Skeleton v-else-if="preview.state.value === PREVIEW_STATES.LOADING" variant="block" />
      <p
        v-else-if="preview.state.value === PREVIEW_STATES.ERROR"
        class="proof-viewer__placeholder"
        role="alert"
      >
        <AppIcon name="warning-triangle" />
        {{ t('finance.transfers.proof.failed') }}
        <BaseButton variant="ghost" size="sm" icon="refresh" @click="preview.load">
          {{ t('finance.checkout.retry') }}
        </BaseButton>
      </p>
      <div v-else-if="ready && isImage" class="proof-viewer__image-frame">
        <img
          class="proof-viewer__image"
          :class="`proof-viewer__image--zoom-${preview.zoom.value}`"
          :src="preview.objectUrl.value"
          :alt="t('finance.transfers.proof.imageAlt', { name: proof.name })"
        />
      </div>
      <iframe
        v-else-if="ready && isPdf"
        class="proof-viewer__pdf"
        :src="preview.objectUrl.value"
        :title="t('finance.transfers.proof.pdfTitle', { name: proof.name })"
      ></iframe>
    </div>

    <p class="proof-viewer__logged">
      <AppIcon name="shield-info" />
      {{ t('finance.transfers.proof.logged') }}
    </p>
  </section>
</template>
