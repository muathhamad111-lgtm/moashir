<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useBrandLogo } from '@/composables/useBrandLogo'
import { useSectionSpy } from '@/composables/useSectionSpy'
import { HOME_ANCHORS, HOME_SPY_ANCHORS } from '@/constants/homeSections'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { useAuthStore } from '@/stores/auth'
import { scrollBehavior } from '@/utils/motion'
import LocaleSwitcher from '@/components/ui/LocaleSwitcher.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'
import PublicNavLinks from './PublicNavLinks.vue'
import PublicNavDrawer from './PublicNavDrawer.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const route = useRoute()
const auth = useAuthStore()
const { themedLogo } = useBrandLogo()
const menuOpen = ref(false)
const isHome = computed(() => route.name === ROUTE_NAMES.PUBLIC_HOME)
const { activeId, isScrolled } = useSectionSpy(() => (isHome.value ? HOME_SPY_ANCHORS : []))

function isActive(link) {
  if (link.routeName) return route.name === link.routeName
  if (!isHome.value) return false
  return (activeId.value ?? HOME_ANCHORS.hero) === link.id
}

function onNavigate(event, link) {
  menuOpen.value = false
  if (link.routeName || !isHome.value) return
  event.preventDefault()
  if (link.id === HOME_ANCHORS.hero) {
    window.scrollTo({ top: 0, behavior: scrollBehavior() })
    return
  }
  document.getElementById(link.id)?.scrollIntoView({ behavior: scrollBehavior() })
}

watch(
  () => route.fullPath,
  () => {
    menuOpen.value = false
  },
)
</script>

<template>
  <header class="public-navbar" :class="{ 'is-scrolled': isScrolled }">
    <span class="public-navbar__backdrop" aria-hidden="true"></span>
    <div class="public-navbar__inner">
      <router-link class="public-navbar__brand" :to="{ name: ROUTE_NAMES.PUBLIC_HOME }">
        <img class="navbar__logo public-navbar__logo" :src="themedLogo" :alt="t('app.name')" />
      </router-link>
      <nav class="public-navbar__menu" :aria-label="t('nav.publicLabel')">
        <PublicNavLinks :is-active="isActive" @navigate="onNavigate" />
      </nav>
      <div class="public-navbar__actions">
        <LocaleSwitcher class="public-navbar__wide" />
        <ThemeToggle class="public-navbar__wide" />
        <template v-if="!auth.isAuthenticated">
          <router-link
            class="btn btn--secondary public-navbar__wide"
            :to="{ name: ROUTE_NAMES.AUTH_REGISTER }"
          >
            {{ t('nav.register') }}
          </router-link>
          <router-link
            class="btn btn--primary public-navbar__login"
            :to="{ name: ROUTE_NAMES.AUTH_LOGIN }"
          >
            <span class="public-navbar__login-full">{{ t('actions.login') }}</span>
            <span class="public-navbar__login-short">{{ t('nav.signInShort') }}</span>
          </router-link>
        </template>
        <router-link v-else class="btn btn--primary" :to="{ name: ROUTE_NAMES.APP_DASHBOARD }">
          {{ t('nav.dashboard') }}
        </router-link>
        <button
          type="button"
          class="btn btn--secondary btn--icon public-navbar__toggle"
          :aria-label="t('nav.toggleMenu')"
          :aria-expanded="menuOpen"
          aria-controls="public-nav-drawer"
          @click="menuOpen = true"
        >
          <AppIcon name="menu" />
        </button>
      </div>
    </div>
    <PublicNavDrawer v-model:open="menuOpen" :is-active="isActive" @navigate="onNavigate" />
  </header>
</template>
