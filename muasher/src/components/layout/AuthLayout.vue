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
import trophy from '@/assets/landing/trophy.webp'
import trophySmall from '@/assets/landing/trophy-sm.webp'

const TROPHY_SIZES = '(max-width: 1279px) 220px, 300px'

const { t } = useI18n()
const route = useRoute()
const { toggleLocale } = useLocale()
const { platformName } = usePlatformIdentity()
const { logoOnDark } = useBrandLogo()

const isMaintenance = computed(() => route.name === ROUTE_NAMES.PUBLIC_MAINTENANCE)
const trophySrcset = `${trophySmall} 420w, ${trophy} 720w`
</script>

<template>
  <div class="auth-layout lp-auth">
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
        <img class="auth-layout__logo" :src="logoOnDark" :alt="platformName" />
        <div class="auth-layout__trophy" aria-hidden="true">
          <img
            :src="trophy"
            :srcset="trophySrcset"
            :sizes="TROPHY_SIZES"
            alt=""
            width="720"
            height="1157"
          />
          <img
            class="auth-layout__reflect"
            :src="trophy"
            :srcset="trophySrcset"
            :sizes="TROPHY_SIZES"
            alt=""
          />
        </div>
      </div>
    </aside>
  </div>
</template>
