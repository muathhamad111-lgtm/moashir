<script setup>
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useBrandLogo } from '@/composables/useBrandLogo'
import { PUBLIC_NAV_ITEMS } from '@/constants/navigation'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { useAuthStore } from '@/stores/auth'
import { useDisclosure } from '@/composables/useDisclosure'
import LocaleSwitcher from '@/components/ui/LocaleSwitcher.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const { t } = useI18n()
const route = useRoute()
const auth = useAuthStore()
const { isOpen, rootRef, toggle, close } = useDisclosure()
const { themedLogo } = useBrandLogo()
const navItems = PUBLIC_NAV_ITEMS

function isActive(item) {
  return route.name === item.routeName
}
</script>

<template>
  <header ref="rootRef" class="navbar public-navbar">
    <router-link class="navbar__brand" :to="{ name: ROUTE_NAMES.PUBLIC_HOME }">
      <img class="navbar__logo" :src="themedLogo" :alt="t('app.name')" />
    </router-link>
    <nav
      :id="'public-nav-menu'"
      class="public-navbar__menu"
      :class="{ 'is-open': isOpen }"
      :aria-label="t('nav.publicLabel')"
    >
      <router-link
        v-for="item in navItems"
        :key="item.id"
        class="public-navbar__link"
        :class="{ 'is-active': isActive(item) }"
        :to="{ name: item.routeName }"
        :aria-current="isActive(item) ? 'page' : undefined"
        @click="close"
      >
        {{ t(item.labelKey) }}
        <span v-if="item.future" class="public-navbar__hint">{{ t('common.comingSoon') }}</span>
      </router-link>
    </nav>
    <div class="navbar__actions">
      <LocaleSwitcher />
      <ThemeToggle />
      <router-link v-if="!auth.isAuthenticated" :to="{ name: ROUTE_NAMES.AUTH_LOGIN }" custom>
        <template #default="{ navigate }">
          <BaseButton variant="primary" size="sm" @click="navigate">
            {{ t('actions.login') }}
          </BaseButton>
        </template>
      </router-link>
      <router-link v-else :to="{ name: ROUTE_NAMES.APP_DASHBOARD }" custom>
        <template #default="{ navigate }">
          <BaseButton variant="primary" size="sm" @click="navigate">
            {{ t('nav.dashboard') }}
          </BaseButton>
        </template>
      </router-link>
      <button
        type="button"
        class="btn btn--ghost btn--sm btn--icon public-navbar__toggle"
        :aria-label="t('nav.toggleMenu')"
        :aria-expanded="isOpen"
        aria-controls="public-nav-menu"
        @click="toggle"
      >
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
      </button>
    </div>
  </header>
</template>
