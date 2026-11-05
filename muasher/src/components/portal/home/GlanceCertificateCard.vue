<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { CERTIFICATE_SEALS } from '@/constants/seals'
import { riyadhYear } from '@/utils/riyadhYear'
import GlanceCard from './GlanceCard.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  certificate: { type: Object, default: null },
  issuingSince: { type: String, default: null },
  isIssuing: { type: Boolean, default: false },
})

const { t, locale } = useI18n()

const seal = computed(() => CERTIFICATE_SEALS[props.certificate?.level] ?? null)
const levelLabel = computed(() => {
  const label = props.certificate?.levelLabel
  return label?.[locale.value] || label?.ar || ''
})
const isValid = computed(() => props.certificate?.status === 'issued')
</script>

<template>
  <GlanceCard
    :title="t('portal.home.glance.certificates.title')"
    icon="award"
    heading-id="glance-certificates-title"
  >
    <div v-if="certificate" class="glance-certificate">
      <img v-if="seal" class="glance-certificate__seal" :src="seal" alt="" />
      <div class="glance-certificate__text">
        <p class="glance-certificate__name">
          {{ t('portal.home.glance.certificates.name', { level: levelLabel }) }}
        </p>
        <p class="glance-certificate__meta">
          <bdi dir="ltr">{{ riyadhYear(certificate.issuedAt) }}</bdi> ·
          <bdi dir="ltr">{{ certificate.number }}</bdi>
        </p>
        <Badge :variant="isValid ? 'success' : 'warning'">
          {{
            isValid
              ? t('portal.home.glance.certificates.valid')
              : t('portal.home.glance.certificates.underReview')
          }}
        </Badge>
      </div>
    </div>
    <p v-else-if="!issuingSince" class="glance-card__empty">
      {{ t('portal.home.glance.certificates.empty') }}
    </p>
    <p v-if="issuingSince" class="glance-certificate__upcoming">
      <AppIcon name="clock" />
      <span>{{
        t(`portal.home.glance.certificates.${isIssuing ? 'issuing' : 'upcoming'}`, {
          year: riyadhYear(issuingSince),
        })
      }}</span>
    </p>
    <template #footer>
      <router-link class="glance-card__link" :to="{ name: ROUTE_NAMES.CERTIFICATES_INDEX }">
        <span>{{ t('portal.home.glance.certificates.link') }}</span>
        <AppIcon name="arrow-right" />
      </router-link>
    </template>
  </GlanceCard>
</template>
