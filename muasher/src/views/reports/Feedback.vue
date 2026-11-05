<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useHead } from '@unhead/vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import PortalAreaHeader from '@/components/portal/PortalAreaHeader.vue'
import FeedbackCriterion from '@/components/reports/FeedbackCriterion.vue'
import { useFeedbackReport } from '@/composables/useFeedbackReport'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { feedbackReportService } from '@/services/feedbackReportService'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { SUPPORTED_LOCALES } from '@/constants/locales'
import { localizedText } from '@/utils/localizedText'
import { percentText } from '@/utils/percentText'
import { isoDate } from '@/utils/datetime'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useAuthStore } from '@/stores/auth'
import { isPortalAudience } from '@/utils/portalAudience'

const { t, n, locale } = useI18n()
const route = useRoute()
const assessmentId = computed(() => Number(route.params.assessmentId))
const exportLocale = ref(locale.value)

const { status, errorMessage, report, load } = useFeedbackReport(assessmentId)
const exports = useSignedDownload({
  errorPrefix: 'feedback.report.errors',
  fallbackKey: 'feedback.toasts.exportFailed',
})

useHead({ title: () => t('feedback.title') })

const auth = useAuthStore()
const isPortal = computed(() => isPortalAudience(auth.roles))
const portalTrail = () => [
  {
    key: 'journey',
    label: t('portal.breadcrumb.journey'),
    to: { name: ROUTE_NAMES.APP_DASHBOARD },
  },
  {
    key: 'certificates',
    label: t('portal.nav.certificates'),
    to: { name: ROUTE_NAMES.CERTIFICATES_INDEX },
  },
]
const staffTrail = () => [
  {
    key: 'request',
    label: t('feedback.staffBreadcrumb.request'),
    to: { name: ROUTE_NAMES.REQUESTS_DETAIL, params: { id: assessmentId.value } },
  },
]
const breadcrumbs = computed(() => [
  ...(isPortal.value ? portalTrail() : staffTrail()),
  { key: 'report', label: t('feedback.title') },
])
const localeOptions = computed(() =>
  SUPPORTED_LOCALES.map((value) => ({ value, label: t(`feedback.report.locales.${value}`) })),
)
const entityName = computed(() => localizedText(report.value?.entity?.name, locale.value))
const completedOn = computed(() => isoDate(report.value?.completedAt))
const hijri = computed(() => localizedText(report.value?.completedOnHijri, locale.value))
const level = computed(() => localizedText(report.value?.certificate?.levelLabel, locale.value))
const percent = computed(() => percentText(n, t, Number(report.value?.percent ?? 0)))

function exportPdf() {
  return exports.download('report', () =>
    feedbackReportService.export(assessmentId.value, { locale: exportLocale.value }),
  )
}
</script>

<template>
  <section class="portal-area feedback-report">
    <PortalAreaHeader
      :breadcrumbs="breadcrumbs"
      :eyebrow="t('feedback.eyebrow')"
      :title="t('feedback.title')"
      :subtitle="t('feedback.subtitle')"
    />

    <div class="portal-area__body feedback-report__body">
      <Skeleton v-if="status === 'loading'" variant="block" :count="2" />
      <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
      <EmptyState
        v-else-if="status === 'missing'"
        icon="folder"
        :title="t('feedback.missing.title')"
        :message="t('feedback.missing.message')"
      />
      <EmptyState
        v-else-if="report && !report.available"
        icon="refresh-dots"
        :title="t('feedback.notYet.title')"
        :message="t('feedback.notYet.message')"
      />
      <template v-else-if="report">
        <section class="portal-surface feedback-report__summary" aria-labelledby="report-summary">
          <h2 id="report-summary" class="portal-surface__title">
            {{ t('feedback.report.summary') }}
            <bdi dir="ltr">{{ report.number }}</bdi>
          </h2>
          <dl class="feedback-report__facts">
            <div>
              <dt>{{ t('feedback.report.entity') }}</dt>
              <dd>{{ entityName }}</dd>
            </div>
            <div>
              <dt>{{ t('feedback.report.completedOn') }}</dt>
              <dd>
                <bdi dir="ltr">{{ completedOn }}</bdi>
                <span v-if="hijri" class="feedback-report__hijri">{{ hijri }}</span>
              </dd>
            </div>
            <div>
              <dt>{{ t('feedback.report.percent') }}</dt>
              <dd>
                <bdi dir="ltr">{{ percent }}</bdi>
              </dd>
            </div>
            <div v-if="report.certificate">
              <dt>{{ t('feedback.report.certificate') }}</dt>
              <dd>
                <bdi dir="ltr">{{ report.certificate.number }}</bdi>
                <span v-if="level"> · {{ level }}</span>
              </dd>
            </div>
          </dl>
          <div class="feedback-report__export">
            <AppSelect
              v-model="exportLocale"
              name="report-locale"
              :label="t('feedback.report.locale')"
              :options="localeOptions"
            />
            <BaseButton
              variant="primary"
              icon="doc"
              :loading="Boolean(exports.busy.report)"
              @click="exportPdf"
            >
              {{ t('feedback.export') }}
            </BaseButton>
          </div>
          <p class="feedback-report__confidentiality">
            <AppIcon name="user-shield" />
            {{ t('feedback.confidentialityNote') }}
          </p>
        </section>

        <FeedbackCriterion
          v-for="criterion in report.criteria"
          :key="criterion.criterionId ?? criterion.code"
          :criterion="criterion"
        />

        <router-link
          class="feedback-report__back"
          :to="{ name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_RESULT, params: { id: assessmentId } }"
        >
          <AppIcon name="arrow-left" />
          {{ t('feedback.backToResult') }}
        </router-link>
      </template>
    </div>
  </section>
</template>
