<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import Tooltip from '@/components/ui/Tooltip.vue'
import { formatRiyadhLongDate } from '@/utils/datetime'
import { ROUTE_NAMES } from '@/constants/routeNames'

const props = defineProps({
  profile: { type: Object, required: true },
  certificate: { type: Object, default: null },
})

const { t, locale } = useI18n()
const date = (value) => formatRiyadhLongDate(value, locale.value)
const validity = computed(() =>
  props.certificate?.validUntil
    ? t('portal.profile.header.validUntil', { date: date(props.certificate.validUntil) })
    : (props.certificate?.number ?? ''),
)
</script>

<template>
  <dl class="profile-facts" :aria-label="t('portal.profile.header.factsLabel')">
    <div class="profile-facts__pair">
      <dt class="profile-facts__label">{{ t('portal.profile.header.reference') }}</dt>
      <dd class="profile-facts__value profile-facts__value--number">
        <bdi dir="ltr">{{ profile.reference }}</bdi>
      </dd>
    </div>
    <div v-if="profile.createdAt" class="profile-facts__pair">
      <dt class="profile-facts__label">{{ t('portal.profile.header.registered') }}</dt>
      <dd class="profile-facts__value">{{ date(profile.createdAt) }}</dd>
    </div>
    <div v-if="profile.verifiedAt" class="profile-facts__pair">
      <dt class="profile-facts__label">{{ t('portal.profile.header.verified') }}</dt>
      <dd class="profile-facts__value">{{ date(profile.verifiedAt) }}</dd>
    </div>
    <div v-if="certificate" class="profile-facts__pair" data-testid="profile-certificate">
      <dt class="profile-facts__label">{{ t('portal.profile.header.certificate') }}</dt>
      <dd class="profile-facts__value profile-facts__value--action">
        <span>{{ validity }}</span>
        <Tooltip :text="t('portal.profile.header.viewCertificate')">
          <router-link
            class="profile-facts__eye"
            :to="{ name: ROUTE_NAMES.CERTIFICATES_INDEX }"
            :aria-label="t('portal.profile.header.viewCertificate')"
            data-testid="profile-view-certificate"
          >
            <AppIcon name="eye" />
          </router-link>
        </Tooltip>
      </dd>
    </div>
  </dl>
</template>
