<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  busy: { type: Boolean, default: false },
  backRoute: { type: String, required: true },
})

const emit = defineEmits(['start'])
const { t } = useI18n()
</script>

<template>
  <p class="checkout__intro">{{ t('finance.checkout.intro') }}</p>
  <p class="checkout__card-note" role="note">
    <AppIcon name="shield-info" />
    {{ t('finance.checkout.cardNote') }}
  </p>
  <div class="checkout__actions">
    <BaseButton
      variant="primary"
      icon="credit-card"
      :loading="busy"
      :disabled="busy"
      @click="emit('start')"
    >
      {{ t('finance.checkout.start') }}
    </BaseButton>
    <RouterLink class="checkout__back" :to="{ name: backRoute }">{{
      t('finance.checkout.back')
    }}</RouterLink>
  </div>
</template>
