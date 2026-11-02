<script setup>
import { useRouter } from 'vue-router'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { useAuthStore } from '@/stores/auth'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  headline: { type: String, default: '' },
  subtitle: { type: String, default: '' },
})

const router = useRouter()
const auth = useAuthStore()

function startAssessment() {
  if (auth.isAuthenticated) {
    router.push({ name: ROUTE_NAMES.APP_DASHBOARD })
    return
  }
  router.push({ name: ROUTE_NAMES.AUTH_REGISTER })
}

function login() {
  router.push({ name: ROUTE_NAMES.AUTH_LOGIN })
}
</script>

<template>
  <section class="hero" aria-labelledby="hero-title">
    <div class="hero__inner">
      <span class="hero__eyebrow">{{ $t('app.name') }}</span>
      <h1 id="hero-title" class="hero__headline">{{ headline || $t('public.hero.headline') }}</h1>
      <p class="hero__subtitle">{{ subtitle || $t('public.hero.subtitle') }}</p>
      <div class="hero__actions">
        <BaseButton variant="primary" size="lg" icon="fa-solid fa-flag-checkered" @click="startAssessment">
          {{ $t('public.hero.start') }}
        </BaseButton>
        <BaseButton variant="secondary" size="lg" icon="fa-solid fa-right-to-bracket" @click="login">
          {{ $t('actions.login') }}
        </BaseButton>
      </div>
    </div>
  </section>
</template>
