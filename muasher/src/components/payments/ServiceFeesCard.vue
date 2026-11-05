<script setup>
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
})

const { t } = useI18n()
</script>

<template>
  <Card>
    <div class="service-fees">
      <h2 class="service-fees__title">{{ title }}</h2>
      <p v-if="description" class="service-fees__description">{{ description }}</p>
      <p class="service-fees__note" role="note">
        <AppIcon name="calculator" />
        <span>{{ t('finance.checkout.feesAtCheckout') }}</span>
      </p>
      <div v-if="$slots.action" class="service-fees__action">
        <slot name="action" />
      </div>
    </div>
  </Card>
</template>
