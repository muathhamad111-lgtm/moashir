<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useEntityStore } from '@/stores/entity'
import { logger } from '@/utils/logger'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const entityStore = useEntityStore()
const incomplete = computed(() => entityStore.profile?.billingComplete === false)
const profileLink = { name: ROUTE_NAMES.ENTITY_PROFILE, hash: '#profile-section-billing' }

onMounted(() => {
  entityStore
    .loadProfile()
    .catch((error) => logger.error(error, { action: 'payments.billingNotice.load' }))
})
</script>

<template>
  <p v-if="incomplete" class="billing-notice" role="note" data-testid="billing-details-notice">
    <AppIcon name="warning-triangle" class="billing-notice__icon" />
    <span class="billing-notice__text">
      <strong class="billing-notice__title">{{ t('finance.checkout.billingNotice.title') }}</strong>
      <span class="billing-notice__body">{{ t('finance.checkout.billingNotice.body') }}</span>
      <RouterLink class="billing-notice__link" :to="profileLink">
        {{ t('finance.checkout.billingNotice.link') }}
      </RouterLink>
    </span>
  </p>
</template>
