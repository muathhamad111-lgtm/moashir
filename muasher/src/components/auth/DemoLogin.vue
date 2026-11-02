<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { demoSessionService } from '@/services/demoSessionService'
import { DEMO_PERSONAS } from '@/constants/demoPersonas'
import ar from '@/i18n/locales/dev/ar.json'
import en from '@/i18n/locales/dev/en.json'

const { t, mergeLocaleMessage } = useI18n()
mergeLocaleMessage('ar', ar)
mergeLocaleMessage('en', en)

const router = useRouter()
const auth = useAuthStore()
const toast = useToast()
const busy = ref(null)

async function enterAs(persona) {
  if (busy.value) return
  busy.value = persona.code
  try {
    const { data } = await demoSessionService.start(persona.code)
    auth.setUser(data)
    await router.push({ name: persona.home })
  } catch {
    toast.error(t('errors.unexpected'))
  } finally {
    busy.value = null
  }
}
</script>

<template>
  <section class="auth-form__demo" :aria-label="t('auth.demo.title')">
    <h2 class="auth-form__demo-title">{{ t('auth.demo.title') }}</h2>
    <p class="auth-form__demo-hint">{{ t('auth.demo.hint') }}</p>
    <div class="auth-form__demo-grid">
      <BaseButton
        v-for="persona in DEMO_PERSONAS"
        :key="persona.code"
        type="button"
        variant="ghost"
        size="sm"
        :loading="busy === persona.code"
        @click="enterAs(persona)"
      >
        {{ t(persona.labelKey) }}
      </BaseButton>
    </div>
  </section>
</template>
