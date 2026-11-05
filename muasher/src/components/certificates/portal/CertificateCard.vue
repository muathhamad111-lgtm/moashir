<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import CertificateStatusBadge from './CertificateStatusBadge.vue'
import { CERTIFICATE_SEALS } from '@/constants/seals'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { isoDate } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'
import { riyadhYear } from '@/utils/riyadhYear'
import AppIcon from '@/components/ui/AppIcon.vue'

const ISSUED = 'issued'
const SUSPENDED = 'suspended'
const REVOKED = 'revoked'

const props = defineProps({
  certificate: { type: Object, required: true },
  downloading: { type: Boolean, default: false },
  copied: { type: Boolean, default: false },
})

const emit = defineEmits(['download', 'copy'])
const { t, locale } = useI18n()

const seal = computed(() => CERTIFICATE_SEALS[props.certificate.level] ?? null)
const level = computed(() => localizedText(props.certificate.levelLabel, locale.value))
const title = computed(() =>
  level.value
    ? t('portal.certificates.cardTitle', { level: level.value })
    : t('portal.certificates.cardTitlePlain'),
)
const entityName = computed(() => localizedText(props.certificate.entityName, locale.value))
const issuedOn = computed(() => isoDate(props.certificate.issuedAt))
const isIssued = computed(() => props.certificate.status === ISSUED)
const isSuspended = computed(() => props.certificate.status === SUSPENDED)
const isPrevious = computed(() => !isIssued.value && !isSuspended.value)
const endedOn = computed(() =>
  isoDate(
    props.certificate.status === REVOKED
      ? props.certificate.revokedAt
      : props.certificate.supersededAt,
  ),
)
</script>

<template>
  <article class="certificate-card" :aria-labelledby="`certificate-${certificate.id}`">
    <div class="certificate-card__seal" aria-hidden="true">
      <img v-if="seal" :src="seal" alt="" />
      <AppIcon v-else name="award" />
    </div>
    <div class="certificate-card__main">
      <div class="certificate-card__badges">
        <CertificateStatusBadge :status="certificate.status" />
        <Badge variant="info">
          <bdi dir="ltr">{{ riyadhYear(certificate.issuedAt) }}</bdi>
        </Badge>
        <Badge v-if="certificate.isTest" variant="warning">{{
          t('portal.certificates.test')
        }}</Badge>
      </div>
      <h3 :id="`certificate-${certificate.id}`" class="certificate-card__title">{{ title }}</h3>
      <p v-if="entityName" class="certificate-card__entity">{{ entityName }}</p>
      <dl class="certificate-card__facts">
        <div>
          <dt>{{ t('portal.certificates.number') }}</dt>
          <dd>
            <bdi dir="ltr">{{ certificate.number }}</bdi>
          </dd>
        </div>
        <div>
          <dt>{{ t('portal.certificates.issuedOn') }}</dt>
          <dd>
            <bdi dir="ltr">{{ issuedOn }}</bdi>
          </dd>
        </div>
        <div v-if="certificate.requestNumber">
          <dt>{{ t('portal.certificates.assessment') }}</dt>
          <dd>
            <bdi dir="ltr">{{ certificate.requestNumber }}</bdi>
          </dd>
        </div>
      </dl>

      <p v-if="isSuspended" class="certificate-card__notice" role="note">
        <AppIcon name="pause" />
        {{ t('portal.certificates.suspendedNotice') }}
      </p>
      <p v-if="isPrevious" class="certificate-card__notice certificate-card__notice--muted">
        <AppIcon name="refresh-dots" />
        <span>{{ t(`portal.certificates.ended.${certificate.status}`, { date: endedOn }) }}</span>
        <span v-if="certificate.revokedReason" class="certificate-card__reason">{{
          t('portal.certificates.reason', { reason: certificate.revokedReason })
        }}</span>
      </p>

      <div v-if="certificate.verificationCode" class="certificate-card__verify">
        <AppIcon name="qr-code" />
        <div class="certificate-card__code">
          <span class="certificate-card__code-label">{{ t('portal.certificates.codeLabel') }}</span>
          <bdi class="certificate-card__code-value" dir="ltr">{{
            certificate.verificationCode
          }}</bdi>
        </div>
        <button
          v-if="certificate.verificationUrl"
          type="button"
          class="certificate-card__copy"
          @click="emit('copy', certificate)"
        >
          <AppIcon :name="copied ? 'check' : 'copy'" />
          {{ copied ? t('portal.certificates.copied') : t('portal.certificates.copyLink') }}
        </button>
      </div>

      <div class="certificate-card__actions">
        <BaseButton
          v-if="isIssued || isSuspended"
          variant="secondary"
          icon="download"
          :loading="downloading"
          :disabled="isSuspended"
          @click="emit('download', certificate)"
        >
          {{ t('portal.certificates.download') }}
        </BaseButton>
        <router-link
          v-if="certificate.verificationCode"
          class="certificate-card__link"
          :to="{
            name: ROUTE_NAMES.VERIFY_CERTIFICATE,
            params: { code: certificate.verificationCode },
          }"
        >
          <AppIcon name="link" />
          {{ t('portal.certificates.publicPage') }}
        </router-link>
        <router-link
          v-if="certificate.requestId"
          class="certificate-card__link"
          :to="{
            name: ROUTE_NAMES.REPORTS_FEEDBACK,
            params: { assessmentId: certificate.requestId },
          }"
        >
          <AppIcon name="doc" />
          {{ t('portal.certificates.feedbackReport') }}
        </router-link>
      </div>
    </div>
  </article>
</template>
