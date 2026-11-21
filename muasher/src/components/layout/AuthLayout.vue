<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useBrandLogo } from '@/composables/useBrandLogo'
import { useLocale } from '@/composables/useLocale'
import { usePlatformIdentity } from '@/composables/usePlatformIdentity'
import { ROUTE_NAMES } from '@/constants/routeNames'
import LandingSprite from '@/components/landing/LandingSprite.vue'
import LandingThemeSwitch from '@/components/landing/LandingThemeSwitch.vue'
import { hasFinePointer } from '@/utils/landing/pointer'

const { t } = useI18n()
const route = useRoute()
const { toggleLocale } = useLocale()
const { platformName } = usePlatformIdentity()
const { logoOnDark } = useBrandLogo()

const isMaintenance = computed(() => route.name === ROUTE_NAMES.PUBLIC_MAINTENANCE)
const customCursor = hasFinePointer()
</script>

<template>
  <div class="auth-layout lp-auth" :class="{ 'lp-has-cursor': customCursor }">
    <LandingSprite />
    <main class="auth-layout__content">
      <div class="auth-layout__meta">
        <router-link
          v-if="!isMaintenance"
          class="auth-layout__link"
          :to="{ name: ROUTE_NAMES.PUBLIC_HOME }"
        >
          <svg class="auth-layout__back" viewBox="0 0 100 100" aria-hidden="true">
            <use href="#lp-i-arrow" />
          </svg>
          {{ t('nav.backToHome') }}
        </router-link>
        <div class="auth-layout__controls">
          <button
            type="button"
            class="lp-top__icon-btn lp-top__locale"
            :aria-label="t('landing.header.language')"
            @click="toggleLocale"
          >
            <span :lang="t('landing.header.languageCode')">{{ t('locale.switchShort') }}</span>
          </button>
          <LandingThemeSwitch />
        </div>
      </div>
      <div class="auth-layout__card">
        <slot />
      </div>
    </main>
    <aside class="auth-layout__brand" :aria-label="t('auth.panel.label')">
      <div class="auth-layout__pattern" aria-hidden="true"></div>
      <div class="auth-layout__brand-inner">
        <div class="auth-layout__emblem">
          <span class="auth-layout__halo" aria-hidden="true"></span>
          <span class="auth-layout__orbit" aria-hidden="true"></span>
          <img class="auth-layout__logo" :src="logoOnDark" :alt="platformName" />
          <span class="auth-layout__sheen" aria-hidden="true"></span>
        </div>
      </div>
    </aside>
  </div>
</template>
