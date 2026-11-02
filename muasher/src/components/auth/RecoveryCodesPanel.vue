<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  codes: { type: Array, required: true },
})

const emit = defineEmits(['continue'])
const { t } = useI18n()
</script>

<template>
  <section class="auth-mfa__recovery" :aria-label="t('auth.mfa.recovery.title')">
    <h2 class="auth-mfa__recovery-title">{{ t('auth.mfa.recovery.title') }}</h2>
    <p class="auth-form__hint">{{ t('auth.mfa.recovery.hint') }}</p>
    <ul class="auth-mfa__recovery-list" dir="ltr">
      <li v-for="code in codes" :key="code" class="auth-mfa__recovery-code">
        <code>{{ code }}</code>
      </li>
    </ul>
    <div class="auth-form__actions">
      <BaseButton type="button" variant="primary" @click="emit('continue')">
        {{ t('auth.mfa.recovery.continue') }}
      </BaseButton>
    </div>
  </section>
</template>
