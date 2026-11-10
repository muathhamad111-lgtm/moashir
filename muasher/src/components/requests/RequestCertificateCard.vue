<script setup>
import { computed, onMounted, ref, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import CertificateStatusBadge from '@/components/certificates/portal/CertificateStatusBadge.vue'
import RequestCertificateFacts from './RequestCertificateFacts.vue'
import { useRequestCertificates } from '@/composables/useRequestCertificates'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { usePermissions } from '@/composables/usePermissions'
import { certificatesService } from '@/services/certificatesService'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { REASON_LENGTH } from '@/constants/entityEnums'
import { formatRiyadhDate } from '@/utils/datetime'

const props = defineProps({
  requestId: { type: Number, required: true },
  completed: { type: Boolean, default: false },
})

const { t, locale } = useI18n()
const { can } = usePermissions()
const certificates = useRequestCertificates(toRef(props, 'requestId'))
const { current, history, status, errorMessage, issuing, canManage, canIssue } = certificates
const downloads = useSignedDownload({
  errorPrefix: 'portal.certificates.errors',
  fallbackKey: 'portal.certificates.errors.failed',
})
const pendingAction = ref(null)
const reasonOpen = computed({
  get: () => pendingAction.value !== null,
  set: (open) => {
    if (!open) pendingAction.value = null
  },
})

const showsIssue = computed(() => props.completed && canIssue.value && !current.value)
const showsFeedback = computed(() => props.completed && can(PERMISSIONS.REPORTS_FEEDBACK_VIEW))

function download(certificate) {
  return downloads.download(certificate.id, () => certificatesService.download(certificate.id))
}

function ask(kind, certificate) {
  pendingAction.value = { kind, certificate }
}

function runPending(reason) {
  const { kind, certificate } = pendingAction.value
  return kind === 'revoke'
    ? certificates.revoke(certificate, reason)
    : certificates.reissue(certificate, reason)
}

function endedOn(certificate) {
  return formatRiyadhDate(certificate.revokedAt || certificate.supersededAt, locale.value)
}

onMounted(certificates.load)
</script>

<template>
  <Card class="request-certificate">
    <template #header>
      <h2 class="card__title-text">{{ t('requests.certificate.title') }}</h2>
    </template>

    <Skeleton v-if="status === 'loading'" variant="text" :count="4" />
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="certificates.load" />
    <template v-else>
      <div v-if="current" class="request-certificate__current" data-certificate="current">
        <RequestCertificateFacts :certificate="current" />
        <div class="request-certificate__actions">
          <BaseButton
            v-if="canManage && current.status === 'issued' && current.hasPdf"
            variant="primary"
            size="sm"
            icon="download"
            :loading="downloads.busy[current.id]"
            @click="download(current)"
            >{{ t('requests.certificate.download') }}</BaseButton
          >
          <BaseButton
            v-if="certificates.canReissue(current)"
            variant="secondary"
            size="sm"
            icon="refresh"
            @click="ask('reissue', current)"
            >{{ t('requests.certificate.reissue') }}</BaseButton
          >
          <BaseButton
            v-if="certificates.canRevoke(current)"
            variant="danger"
            size="sm"
            icon="ban"
            @click="ask('revoke', current)"
            >{{ t('requests.certificate.revoke') }}</BaseButton
          >
        </div>
      </div>
      <p v-else-if="!completed" class="request-certificate__note">
        {{ t('requests.certificate.afterCompletion') }}
      </p>
      <p v-else-if="canManage" class="request-certificate__note">
        {{ t('requests.certificate.none') }}
      </p>

      <div v-if="showsIssue" class="request-certificate__issue">
        <p class="request-certificate__note">{{ t('requests.certificate.issueNote') }}</p>
        <BaseButton
          variant="primary"
          size="sm"
          icon="award"
          :loading="issuing"
          @click="certificates.issue"
          >{{ t('requests.certificate.issue') }}</BaseButton
        >
      </div>

      <section
        v-if="history.length"
        class="request-certificate__history"
        aria-labelledby="request-certificate-history"
      >
        <h3 id="request-certificate-history" class="request-certificate__subtitle">
          {{ t('requests.certificate.history') }}
        </h3>
        <ul class="request-certificate__history-list">
          <li v-for="item in history" :key="item.id" class="request-certificate__history-item">
            <span class="request-certificate__history-head">
              <bdi dir="ltr">{{ item.number }}</bdi>
              <CertificateStatusBadge :status="item.status" />
            </span>
            <span v-if="endedOn(item)" class="request-certificate__history-date">
              <bdi>{{ endedOn(item) }}</bdi>
            </span>
            <span v-if="item.revokedReason" class="request-certificate__history-reason">
              {{ t('requests.certificate.reason', { reason: item.revokedReason }) }}
            </span>
            <BaseButton
              v-if="certificates.canReissue(item) && !current"
              variant="ghost"
              size="sm"
              icon="refresh"
              @click="ask('reissue', item)"
              >{{ t('requests.certificate.reissue') }}</BaseButton
            >
          </li>
        </ul>
      </section>

      <RouterLink
        v-if="showsFeedback"
        class="request-certificate__link"
        :to="{ name: ROUTE_NAMES.REPORTS_FEEDBACK, params: { assessmentId: requestId } }"
      >
        {{ t('requests.certificate.feedbackReport') }}
      </RouterLink>
    </template>

    <ReasonDialog
      v-model="reasonOpen"
      :title="
        pendingAction?.kind === 'revoke'
          ? t('requests.certificate.revokeTitle')
          : t('requests.certificate.reissueTitle')
      "
      :subtitle="pendingAction?.certificate?.number ?? ''"
      :submit-label="
        pendingAction?.kind === 'revoke'
          ? t('requests.certificate.revoke')
          : t('requests.certificate.reissue')
      "
      :reason-hint="
        pendingAction?.kind === 'revoke'
          ? t('requests.certificate.revokeHint', { min: REASON_LENGTH.MIN })
          : ''
      "
      :destructive="pendingAction?.kind === 'revoke'"
      :min-length="REASON_LENGTH.MIN"
      :action="runPending"
      action-name="requests.certificate.decision"
    />
    <ReasonDialog
      v-model="certificates.reasonPrompt.open.value"
      :title="t('review.reasonTitle')"
      :submit-label="t('actions.confirm')"
      :action="certificates.reasonPrompt.provide"
      action-name="requests.certificate.superAdminReason"
    />
  </Card>
</template>
