<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { CODE_FAILURES } from '@/constants/signIn'

const props = defineProps({
  failure: { type: Object, required: true },
  requestRoute: { type: Object, required: true },
})

const { t } = useI18n()
const isLocked = computed(() => props.failure.kind === CODE_FAILURES.LOCKED)
const body = computed(() => {
  const { kind, minutes } = props.failure
  if (isLocked.value && minutes) return t('auth.failures.retryIn', { count: minutes }, minutes)
  return t(`auth.otp.code.ended.${kind}.body`)
})
</script>

<template>
  <div class="auth-otp__ended">
    <div
      class="auth-status auth-status--danger auth-failure"
      :data-failure="failure.kind"
      role="alert"
    >
      <AppIcon name="warning-triangle" class="auth-status__icon" />
      <p class="auth-status__title">{{ t(`auth.otp.code.ended.${failure.kind}.title`) }}</p>
      <p class="auth-status__text">{{ body }}</p>
    </div>
    <div v-if="!isLocked" class="auth-form__actions">
      <router-link class="btn btn--primary auth-otp__request-new" :to="requestRoute">
        {{ t('auth.otp.code.requestNew') }}
      </router-link>
    </div>
  </div>
</template>
