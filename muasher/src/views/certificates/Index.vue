<script setup>
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import PortalAreaHeader from '@/components/portal/PortalAreaHeader.vue'
import CertificateCard from '@/components/certificates/portal/CertificateCard.vue'
import IssuingCertificateCard from '@/components/certificates/portal/IssuingCertificateCard.vue'
import VerifyGuideCards from '@/components/certificates/portal/VerifyGuideCards.vue'
import { useCertificatesHub } from '@/composables/useCertificatesHub'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { useClipboard } from '@/composables/useClipboard'
import { certificatesService } from '@/services/certificatesService'
import { CERTIFICATE_TABS } from '@/constants/certificates'
import AppIcon from '@/components/ui/AppIcon.vue'

const PANEL_ID = 'certificates-panel'

const { t } = useI18n()
const hub = useCertificatesHub()
const downloads = useSignedDownload({
  errorPrefix: 'portal.certificates.errors',
  fallbackKey: 'portal.certificates.errors.failed',
})
const clipboard = useClipboard()

useHead({ title: () => t('portal.certificates.eyebrow') })

function download(certificate) {
  return downloads.download(certificate.id, () => certificatesService.download(certificate.id))
}

function copyLink(certificate) {
  return clipboard.copy(certificate.id, certificate.verificationUrl)
}
</script>

<template>
  <section class="portal-area certificates-hub">
    <PortalAreaHeader
      band
      :eyebrow="t('portal.certificates.eyebrow')"
      :title="t('portal.certificates.title')"
      :subtitle="t('portal.certificates.subtitle')"
      :tabs="hub.tabs.value"
      :tab="hub.tab.value"
      :tabs-label="t('portal.certificates.tabsLabel')"
      :panel-id="PANEL_ID"
      @update:tab="hub.tab.value = $event"
    />

    <div class="portal-area__body certificates-hub__body">
      <div
        :id="PANEL_ID"
        class="portal-area__panel"
        role="tabpanel"
        :aria-labelledby="`${PANEL_ID}-tab-${hub.tab.value}`"
      >
        <h2 class="portal-area__section-title">
          {{ t(`portal.certificates.sections.${hub.tab.value}`) }}
        </h2>
        <Skeleton v-if="hub.status.value === 'loading'" variant="block" :count="2" />
        <ErrorState
          v-else-if="hub.status.value === 'error'"
          :message="hub.errorMessage.value"
          @retry="hub.load"
        />
        <template v-else-if="hub.tab.value === CERTIFICATE_TABS.ISSUING">
          <EmptyState
            v-if="!hub.issuing.value.length"
            icon="clock"
            :title="t('portal.certificates.empty.in_progress.title')"
            :message="t('portal.certificates.empty.in_progress.message')"
          />
          <IssuingCertificateCard
            v-for="request in hub.issuing.value"
            :key="request.id"
            :request="request"
          />
        </template>
        <template v-else>
          <EmptyState
            v-if="!hub.visible.value.length"
            icon="award"
            :title="t(`portal.certificates.empty.${hub.tab.value}.title`)"
            :message="t(`portal.certificates.empty.${hub.tab.value}.message`)"
          />
          <CertificateCard
            v-for="certificate in hub.visible.value"
            :key="certificate.id"
            :certificate="certificate"
            :downloading="Boolean(downloads.busy[certificate.id])"
            :copied="clipboard.copiedKey.value === certificate.id"
            @download="download"
            @copy="copyLink"
          />
          <p
            v-if="hub.tab.value === CERTIFICATE_TABS.VALID && hub.issuing.value.length"
            class="portal-area__note certificates-hub__issuing-note"
          >
            <AppIcon name="clock" />
            <span class="certificates-hub__issuing-text">
              {{ t('portal.certificates.issuingNote', hub.issuing.value.length) }}
            </span>
            <BaseButton variant="ghost" size="sm" @click="hub.tab.value = CERTIFICATE_TABS.ISSUING">
              {{ t('portal.certificates.showIssuing') }}
            </BaseButton>
          </p>
        </template>
      </div>

      <VerifyGuideCards />
    </div>
  </section>
</template>
