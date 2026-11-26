<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useBrandLogo } from '@/composables/useBrandLogo'
import { useSiteBranding } from '@/composables/useSiteBranding'
import { useSectionSpy } from '@/composables/useSectionSpy'
import { usePublicNavLinks } from '@/composables/usePublicNavLinks'
import { usePlatformIdentity } from '@/composables/usePlatformIdentity'
import { useLocale } from '@/composables/useLocale'
import { useScrollFrame } from '@/composables/landing/useScrollFrame'
import { isOverDeepBand } from '@/utils/landing/deepBands'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { useAuthStore } from '@/stores/auth'
import LandingLink from '@/components/landing/LandingLink.vue'
import LandingThemeSwitch from '@/components/landing/LandingThemeSwitch.vue'
import LandingNavGroup from '@/components/landing/LandingNavGroup.vue'

const DEEP_LINE = 38
const READING_LINE = 0.35

const { t } = useI18n()
const route = useRoute()
const auth = useAuthStore()
const { toggleLocale } = useLocale()
const { logoOnDark, logoOnLight } = useBrandLogo()
const { siteLogo } = useSiteBranding()
const { platformName } = usePlatformIdentity()
const { links, spyAnchors } = usePublicNavLinks()
const { activeId, isScrolled } = useSectionSpy(() => spyAnchors.value)

const open = ref(false)
const onDeep = ref(false)
const current = ref(null)
const lightLogo = computed(() => siteLogo.value || logoOnLight)
const darkLogo = computed(() => siteLogo.value || logoOnDark)
const homeLabel = computed(() => t('landing.header.home', { name: platformName.value }))

function passed(id) {
  const top = id ? document.getElementById(id)?.getBoundingClientRect().top : null
  return top !== null && top !== undefined && top <= window.innerHeight * READING_LINE
}

useScrollFrame(() => {
  onDeep.value = isOverDeepBand(DEEP_LINE)
  current.value = passed(activeId.value) ? activeId.value : null
})

function close() {
  open.value = false
}

function onKeydown(event) {
  if (event.key === 'Escape' && open.value) close()
}

watch(() => route.fullPath, close)
</script>

<template>
  <header
    class="lp-top"
    :class="{ 'is-solid': isScrolled, 'is-on-deep': onDeep, 'is-nav-open': open }"
    @keydown="onKeydown"
  >
    <div class="lp-wrap lp-top__bar">
      <LandingLink class="lp-top__brand" to="/#hero" :aria-label="homeLabel" @navigate="close">
        <img class="lp-only-light" :src="lightLogo" :alt="platformName" width="104" height="60" />
        <img class="lp-only-dark" :src="darkLogo" :alt="platformName" width="104" height="60" />
      </LandingLink>

      <nav id="lp-nav" class="lp-top__nav" :aria-label="t('nav.publicLabel')">
        <template v-for="link in links" :key="link.key">
          <LandingNavGroup
            v-if="link.children"
            :group="link"
            :current="current"
            @navigate="close"
          />
          <a
            v-else-if="link.href"
            class="lp-top__link"
            :href="link.href"
            target="_blank"
            rel="noopener noreferrer"
          >
            {{ link.label }}
          </a>
          <LandingLink
            v-else
            class="lp-top__link"
            :class="{ 'is-active': link.anchor && current === link.anchor }"
            :aria-current="link.anchor && current === link.anchor ? 'true' : undefined"
            :to="link.anchor ? `/#${link.anchor}` : String(link.to)"
            @navigate="close"
          >
            {{ link.label }}
          </LandingLink>
        </template>
        <div v-if="!auth.isAuthenticated" class="lp-top__nav-actions">
          <router-link
            class="lp-btn lp-btn--fill lp-btn--sm"
            :to="{ name: ROUTE_NAMES.AUTH_REGISTER }"
          >
            {{ t('landing.header.start') }}
          </router-link>
          <router-link
            class="lp-btn lp-btn--line lp-btn--sm"
            :to="{ name: ROUTE_NAMES.AUTH_LOGIN }"
          >
            {{ t('actions.login') }}
          </router-link>
        </div>
      </nav>

      <div class="lp-top__actions">
        <template v-if="!auth.isAuthenticated">
          <router-link
            class="lp-btn lp-btn--fill lp-btn--sm lp-top__wide lp-top__start"
            :to="{ name: ROUTE_NAMES.AUTH_REGISTER }"
          >
            {{ t('landing.header.start') }}
          </router-link>
          <router-link
            class="lp-btn lp-btn--line lp-btn--sm lp-top__wide"
            :to="{ name: ROUTE_NAMES.AUTH_LOGIN }"
          >
            {{ t('actions.login') }}
          </router-link>
        </template>
        <router-link
          v-else
          class="lp-btn lp-btn--fill lp-btn--sm lp-top__wide"
          :to="{ name: ROUTE_NAMES.APP_DASHBOARD }"
        >
          {{ t('nav.dashboard') }}
        </router-link>
        <span class="lp-top__sep" aria-hidden="true"></span>
        <button
          type="button"
          class="lp-top__icon-btn lp-top__locale"
          :aria-label="t('landing.header.language')"
          @click="toggleLocale"
        >
          <span :lang="t('landing.header.languageCode')">{{ t('locale.switchShort') }}</span>
        </button>
        <LandingThemeSwitch />
        <button
          type="button"
          class="lp-top__icon-btn lp-top__burger"
          :aria-expanded="open ? 'true' : 'false'"
          aria-controls="lp-nav"
          :aria-label="t('nav.toggleMenu')"
          @click="open = !open"
        >
          <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#lp-i-menu" /></svg>
        </button>
      </div>
    </div>
  </header>
</template>
