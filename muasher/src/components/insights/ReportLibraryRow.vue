<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { LIBRARY_ITEM_ICONS, LIBRARY_ITEM_KINDS } from '@/constants/insights'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { isMetalTier } from '@/utils/levelTier'
import { LEVEL_CERTIFICATE_STATUS } from '@/constants/levels'

const props = defineProps({
  item: { type: Object, required: true },
  assessmentId: { type: Number, required: true },
  result: { type: Object, required: true },
  staff: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['board-report', 'feedback-pdf'])
const { t } = useI18n()
const K = LIBRARY_ITEM_KINDS
const isCertificate = computed(() => props.item.kind === K.CERTIFICATE)
const certificate = computed(() => (isCertificate.value ? props.result.certificate : null))
const metalLevel = computed(() =>
  certificate.value?.status === LEVEL_CERTIFICATE_STATUS && isMetalTier(certificate.value.level)
    ? certificate.value.level
    : null,
)
const hasFeedbackPdf = computed(
  () => props.item.kind === K.FEEDBACK_REPORT && Boolean(props.item.pdfUrl),
)
const certificateLabel = computed(() =>
  props.item.status
    ? t(`insights.library.certificateStatus.${props.item.status}`)
    : t('insights.library.noCertificate'),
)
</script>

<template>
  <li class="library-row" :data-kind="item.kind">
    <span class="library-row__name">
      <AppIcon :name="LIBRARY_ITEM_ICONS[item.kind]" />
      {{ t(`insights.library.items.${item.kind}`) }}
      <template v-if="certificate?.number">
        <span aria-hidden="true">·</span>
        <bdi dir="ltr" data-testid="library-certificate-number">{{ certificate.number }}</bdi>
      </template>
      <template v-if="metalLevel">
        <span aria-hidden="true">·</span>
        <span>{{ t(`level.names.${metalLevel}`) }}</span>
      </template>
    </span>
    <span class="library-row__actions">
      <span
        v-if="isCertificate"
        class="library-row__status"
        :class="{ 'library-row__status--valid': item.available }"
      >
        {{ certificateLabel }}
      </span>
      <router-link
        v-if="item.kind === K.FEEDBACK_REPORT && item.available"
        class="library-row__link"
        :to="{ name: ROUTE_NAMES.REPORTS_FEEDBACK, params: { assessmentId } }"
      >
        {{ t('insights.library.view') }}
      </router-link>
      <button
        v-if="hasFeedbackPdf"
        type="button"
        class="library-row__link"
        :disabled="busy"
        :aria-label="t('insights.library.feedbackPdf', { number: result.number })"
        data-testid="library-feedback-pdf"
        @click="emit('feedback-pdf', item.pdfUrl)"
      >
        {{ t('insights.library.pdf') }}
      </button>
      <button
        v-if="item.kind === K.BOARD_REPORT && item.available"
        type="button"
        class="library-row__link"
        :disabled="busy"
        @click="emit('board-report', assessmentId)"
      >
        {{ t('insights.library.pdf') }}
      </button>
      <span v-else-if="item.kind === K.BOARD_REPORT" class="library-row__muted">
        {{ t('insights.library.notAvailable') }}
      </span>
      <router-link
        v-if="isCertificate && item.available && !staff"
        class="library-row__link"
        :to="{ name: ROUTE_NAMES.CERTIFICATES_INDEX }"
      >
        {{ t('insights.library.view') }}
      </router-link>
    </span>
  </li>
</template>
