<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import CertificateStatusBadge from '@/components/certificates/portal/CertificateStatusBadge.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { formatRiyadhDate } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  certificate: { type: Object, required: true },
})

const { t, locale } = useI18n()
const level = computed(
  () =>
    localizedText(props.certificate.levelLabel, locale.value) ||
    t('requests.certificate.participation'),
)
const issuedOn = computed(() => formatRiyadhDate(props.certificate.issuedAt, locale.value))
</script>

<template>
  <dl class="request-certificate__facts">
    <div>
      <dt>{{ t('requests.certificate.number') }}</dt>
      <dd>
        <bdi dir="ltr">{{ certificate.number }}</bdi>
      </dd>
    </div>
    <div>
      <dt>{{ t('requests.certificate.status') }}</dt>
      <dd><CertificateStatusBadge :status="certificate.status" /></dd>
    </div>
    <div>
      <dt>{{ t('requests.certificate.level') }}</dt>
      <dd>{{ level }}</dd>
    </div>
    <div v-if="issuedOn">
      <dt>{{ t('requests.certificate.issuedOn') }}</dt>
      <dd>
        <bdi dir="ltr">{{ issuedOn }}</bdi>
      </dd>
    </div>
    <div v-if="certificate.issuedBy?.name">
      <dt>{{ t('requests.certificate.issuedBy') }}</dt>
      <dd>{{ certificate.issuedBy.name }}</dd>
    </div>
    <div v-if="certificate.reissueReason">
      <dt>{{ t('requests.certificate.reissueReason') }}</dt>
      <dd>{{ certificate.reissueReason }}</dd>
    </div>
    <div v-if="certificate.verificationCode">
      <dt>{{ t('requests.certificate.verification') }}</dt>
      <dd>
        <RouterLink
          :to="{
            name: ROUTE_NAMES.VERIFY_CERTIFICATE,
            params: { code: certificate.verificationCode.replaceAll(' ', '') },
          }"
          target="_blank"
          rel="noopener"
        >
          <bdi dir="ltr">{{ certificate.verificationCode }}</bdi>
        </RouterLink>
      </dd>
    </div>
  </dl>
  <p v-if="certificate.status === 'suspended'" class="request-certificate__note" role="note">
    {{ t('requests.certificate.suspendedNote') }}
  </p>
</template>
