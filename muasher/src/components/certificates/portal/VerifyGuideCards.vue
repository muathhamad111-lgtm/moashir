<script setup>
import { useI18n } from 'vue-i18n'
import CertificateStatusBadge from './CertificateStatusBadge.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'

const STATUSES = ['issued', 'suspended', 'superseded', 'revoked']

const { t } = useI18n()
</script>

<template>
  <section class="verify-guide" aria-labelledby="verify-guide-title">
    <h2 id="verify-guide-title" class="portal-area__section-title">
      {{ t('portal.certificates.guide.title') }}
    </h2>
    <ul class="verify-guide__cards">
      <li class="verify-guide__card">
        <span class="verify-guide__icon" aria-hidden="true"><AppIcon name="qr-code" /></span>
        <h3 class="verify-guide__card-title">{{ t('portal.certificates.guide.qr.title') }}</h3>
        <p class="verify-guide__text">{{ t('portal.certificates.guide.qr.text') }}</p>
      </li>
      <li class="verify-guide__card">
        <span class="verify-guide__icon" aria-hidden="true"><AppIcon name="search" /></span>
        <h3 class="verify-guide__card-title">{{ t('portal.certificates.guide.code.title') }}</h3>
        <p class="verify-guide__text">{{ t('portal.certificates.guide.code.text') }}</p>
        <router-link class="verify-guide__link" :to="{ name: ROUTE_NAMES.VERIFY }">
          {{ t('portal.certificates.guide.code.link') }}
        </router-link>
      </li>
      <li class="verify-guide__card">
        <span class="verify-guide__icon" aria-hidden="true"><AppIcon name="shield-info" /></span>
        <h3 class="verify-guide__card-title">{{ t('portal.certificates.guide.status.title') }}</h3>
        <ul class="verify-guide__statuses">
          <li v-for="status in STATUSES" :key="status">
            <CertificateStatusBadge :status="status" />
          </li>
        </ul>
      </li>
    </ul>
  </section>
</template>
