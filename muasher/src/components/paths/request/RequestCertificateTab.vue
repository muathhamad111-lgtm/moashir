<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { isoDate } from '@/utils/datetime'
import { statusLabelKey } from '@/utils/canonicalStatus'
import Badge from '@/components/ui/Badge.vue'
import LevelEmblem from '@/components/level/LevelEmblem.vue'

const props = defineProps({
  record: { type: Object, required: true },
})

const { t } = useI18n()
const certificate = computed(() => props.record.certificate)
const level = computed(() => certificate.value?.level ?? props.record.level ?? null)
</script>

<template>
  <section class="request-certificate-tab" data-testid="request-tab-certificate">
    <LevelEmblem v-if="level" :tier="level" size="l" />
    <div v-if="certificate" class="request-certificate-tab__copy">
      <h2 class="request-certificate-tab__title">
        {{ level ? t(`level.names.${level}`) : t('paths.request.certificate.title') }}
      </h2>
      <p class="request-certificate-tab__badges">
        <Badge :variant="certificate.isExpired ? 'danger' : 'success'">
          {{
            certificate.isExpired
              ? t('paths.request.certificate.expired')
              : t(statusLabelKey(certificate.status))
          }}
        </Badge>
      </p>
      <dl class="request-overview__facts">
        <div class="request-overview__fact">
          <dt>{{ t('paths.request.certificate.number') }}</dt>
          <dd>
            <bdi dir="ltr">{{ certificate.number }}</bdi>
          </dd>
        </div>
        <div class="request-overview__fact">
          <dt>{{ t('paths.request.certificate.issuedAt') }}</dt>
          <dd>
            <bdi dir="ltr">{{ isoDate(certificate.issuedAt) || '—' }}</bdi>
          </dd>
        </div>
        <div class="request-overview__fact" data-testid="certificate-valid-until">
          <dt>{{ t('paths.request.certificate.validUntil') }}</dt>
          <dd>
            <bdi dir="ltr">{{ certificate.validUntil || '—' }}</bdi>
          </dd>
        </div>
      </dl>
      <router-link class="btn btn--secondary" :to="{ name: ROUTE_NAMES.CERTIFICATES_INDEX }">
        {{ t('paths.request.certificate.open') }}
      </router-link>
    </div>
  </section>
</template>
