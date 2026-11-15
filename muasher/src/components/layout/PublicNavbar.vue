<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useBrandLogo } from '@/composables/useBrandLogo'
import { useSectionSpy } from '@/composables/useSectionSpy'
import { usePublicNavLinks } from '@/composables/usePublicNavLinks'
import { usePlatformIdentity } from '@/composables/usePlatformIdentity'
import { HOME_ANCHORS } from '@/constants/homeSections'
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
const router = useRouter()
const auth = useAuthStore()
const { themedLogo } = useBrandLogo()
const menuOpen = ref(false)
const isHome = computed(() => route.name === ROUTE_NAMES.PUBLIC_HOME)
const { links, spyAnchors } = usePublicNavLinks()
const { platformName } = usePlatformIdentity()
const { activeId, isScrolled } = useSectionSpy(() => (isHome.value ? spyAnchors.value : []))

function isActive(link) {
  if (link.anchor) return isHome.value && (activeId.value ?? HOME_ANCHORS.hero) === link.anchor
  if (!link.to) return false
  return route.path === router.resolve(link.to).path
}

function onNavigate(event, link) {
  menuOpen.value = false
  if (!link.anchor || !isHome.value) return
  event.preventDefault()
  if (link.anchor === HOME_ANCHORS.hero) {
    window.scrollTo({ top: 0, behavior: scrollBehavior() })
    return
  }
  document.getElementById(link.anchor)?.scrollIntoView({ behavior: scrollBehavior() })
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
        <img class="navbar__logo public-navbar__logo" :src="themedLogo" :alt="platformName" />
      </router-link>
      <nav class="public-navbar__menu" :aria-label="t('nav.publicLabel')">
        <PublicNavLinks :links="links" :is-active="isActive" @navigate="onNavigate" />
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
    <PublicNavDrawer
      v-model:open="menuOpen"
      :links="links"
      :is-active="isActive"
      @navigate="onNavigate"
    />
  </header>
</template>
