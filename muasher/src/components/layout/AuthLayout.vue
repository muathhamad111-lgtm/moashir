<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useBrandLogo } from '@/composables/useBrandLogo'
import { ROUTE_NAMES } from '@/constants/routeNames'
import LocaleSwitcher from '@/components/ui/LocaleSwitcher.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'
import BrandPattern from '@/components/ui/BrandPattern.vue'
import AuthValuePanel from '@/components/auth/AuthValuePanel.vue'

const { t } = useI18n()
const route = useRoute()
const { logoOnDark } = useBrandLogo()

const isMaintenance = computed(() => route.name === ROUTE_NAMES.PUBLIC_MAINTENANCE)
</script>

<template>
  <div class="auth-layout">
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
    <aside class="auth-layout__brand brand-surface" :aria-label="t('auth.panel.label')">
      <BrandPattern variant="lattice" />
      <div class="auth-layout__brand-inner">
        <img class="auth-layout__logo" :src="logoOnDark" :alt="t('app.name')" />
        <AuthValuePanel />
      </div>
    </aside>
  </div>
</template>
