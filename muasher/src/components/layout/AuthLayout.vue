<script setup>
import { computed, useTemplateRef } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useLocale } from '@/composables/useLocale'
import { useMediaQuery } from '@/composables/useMediaQuery'
import { useAuthPageTransition } from '@/composables/auth/useAuthPageTransition'
import { useAuthViewport } from '@/composables/auth/useAuthViewport'
import { useIdleRouteWarmup, useRouteIntentPrefetch } from '@/composables/useRoutePrefetch'
import { DESKTOP_QUERY } from '@/constants/breakpoints'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AuthBrandPanel from '@/components/layout/AuthBrandPanel.vue'
import LandingSprite from '@/components/landing/LandingSprite.vue'
import LandingThemeSwitch from '@/components/landing/LandingThemeSwitch.vue'
import { hasFinePointer } from '@/utils/landing/pointer'

const { t } = useI18n()
const route = useRoute()
const { toggleLocale } = useLocale()
const isSplit = useMediaQuery(DESKTOP_QUERY)

const rootRef = useTemplateRef('root')
const viewportRef = useTemplateRef('viewport')
const contentRef = useTemplateRef('content')
const authViewport = useAuthViewport(viewportRef, contentRef)
const { onBeforeLeave, onLeave, onEnter } = useAuthPageTransition(authViewport)

// Only the pages a visitor switches to by a link are warmed; the code steps follow a server reply.
useIdleRouteWarmup([
  ROUTE_NAMES.AUTH_LOGIN,
  ROUTE_NAMES.AUTH_REGISTER,
  ROUTE_NAMES.AUTH_FORGOT_PASSWORD,
])
useRouteIntentPrefetch(rootRef)

const isMaintenance = computed(() => route.name === ROUTE_NAMES.PUBLIC_MAINTENANCE)
const customCursor = hasFinePointer()
</script>

<template>
  <div ref="root" class="auth-layout lp-auth" :class="{ 'lp-has-cursor': customCursor }">
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
        <div ref="viewport" class="auth-layout__viewport">
          <div ref="content" class="auth-layout__page">
            <Transition
              mode="out-in"
              :css="false"
              @before-leave="onBeforeLeave"
              @leave="onLeave"
              @enter="onEnter"
            >
              <slot />
            </Transition>
          </div>
        </div>
      </div>
    </main>
    <AuthBrandPanel :key="isSplit ? 'split' : 'stacked'" :interactive="isSplit" />
  </div>
</template>
