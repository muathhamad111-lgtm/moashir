<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useBrandLogo } from '@/composables/useBrandLogo'
import { ROUTE_NAMES } from '@/constants/routeNames'
import LocaleSwitcher from '@/components/ui/LocaleSwitcher.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'
import IllustrationSlot from '@/components/public/IllustrationSlot.vue'

const { t } = useI18n()
const route = useRoute()
const { logoOnDark } = useBrandLogo()

const isRegister = computed(() => route.name === ROUTE_NAMES.AUTH_REGISTER)
const isLogin = computed(() => route.name === ROUTE_NAMES.AUTH_LOGIN)
const isMaintenance = computed(() => route.name === ROUTE_NAMES.PUBLIC_MAINTENANCE)
const illustrationAlt = computed(() =>
  isRegister.value ? t('illustrations.register') : t('illustrations.login'),
)
const illustrationSrc = computed(() => {
  if (isRegister.value) return '/generated/register.jpg'
  if (isLogin.value) return '/generated/login.jpg'
  return null
})
</script>

<template>
  <div class="auth-layout">
    <aside class="auth-layout__brand">
      <img class="auth-layout__logo" :src="logoOnDark" :alt="t('app.name')" />
      <p class="auth-layout__tagline">{{ t('app.tagline') }}</p>
      <IllustrationSlot
        class="auth-layout__illustration"
        :src="illustrationSrc"
        :alt="illustrationAlt"
        variant="compact"
      />
    </aside>
    <main class="auth-layout__content">
      <div class="auth-layout__card">
        <slot />
      </div>
      <div class="auth-layout__meta">
        <LocaleSwitcher />
        <ThemeToggle />
        <router-link
          v-if="!isMaintenance"
          class="auth-layout__link"
          :to="{ name: ROUTE_NAMES.PUBLIC_HOME }"
        >
          {{ t('nav.backToHome') }}
        </router-link>
      </div>
    </main>
  </div>
</template>
