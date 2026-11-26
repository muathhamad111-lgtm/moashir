<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'

defineProps({
  duplicates: { type: Array, required: true },
})

const { t } = useI18n()
</script>

<template>
  <section class="duplicate-proof" role="note">
    <p class="duplicate-proof__title">
      <AppIcon name="copy" />
      {{ t('finance.transfers.duplicate.title') }}
    </p>
    <ul class="duplicate-proof__list">
      <li v-for="item in duplicates" :key="item.paymentId">
        <span>{{ t('finance.transfers.duplicate.usedIn') }}</span>
        <bdi dir="ltr">{{ item.reference }}</bdi>
        <RouterLink
          class="duplicate-proof__link"
          :to="{ name: ROUTE_NAMES.FINANCE_TRANSFER, params: { id: item.paymentId } }"
        >
          <AppIcon name="link" />
          {{ t('finance.transfers.duplicate.open') }}
          <span class="duplicate-proof__sr">{{ item.reference }}</span>
        </RouterLink>
      </li>
    </ul>
  </section>
</template>
