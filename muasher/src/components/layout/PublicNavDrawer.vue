<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { useAuthStore } from '@/stores/auth'
import { useBrandLogo } from '@/composables/useBrandLogo'
import { useFocusTrap } from '@/composables/useFocusTrap'
import LocaleSwitcher from '@/components/ui/LocaleSwitcher.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'
import PublicNavLinks from './PublicNavLinks.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  open: { type: Boolean, required: true },
  isActive: { type: Function, required: true },
})

const emit = defineEmits(['update:open', 'navigate'])

const { t } = useI18n()
const auth = useAuthStore()
const { themedLogo } = useBrandLogo()
const panelRef = ref(null)
const isOpen = computed(() => props.open)

function close() {
  emit('update:open', false)
}

const { onKeydown } = useFocusTrap(panelRef, { isActive: isOpen, onEscape: close })
</script>

<template>
  <Teleport to="body">
    <Transition name="nav-drawer">
      <div v-if="open" class="nav-drawer" @keydown="onKeydown">
        <div class="nav-drawer__scrim" @click="close"></div>
        <div
          id="public-nav-drawer"
          ref="panelRef"
          class="nav-drawer__panel"
          role="dialog"
          aria-modal="true"
          :aria-label="t('nav.menuTitle')"
        >
          <div class="nav-drawer__head">
            <img class="nav-drawer__logo" :src="themedLogo" :alt="t('app.name')" />
            <button
              type="button"
              class="nav-drawer__close"
              :aria-label="t('nav.closeMenu')"
              @click="close"
            >
              <AppIcon name="x" />
            </button>
          </div>
          <nav class="nav-drawer__nav" :aria-label="t('nav.publicLabel')">
            <PublicNavLinks
              variant="drawer"
              :is-active="isActive"
              @navigate="(event, link) => emit('navigate', event, link)"
            />
          </nav>
          <div class="nav-drawer__prefs">
            <LocaleSwitcher />
            <ThemeToggle />
          </div>
          <div class="nav-drawer__auth">
            <template v-if="!auth.isAuthenticated">
              <router-link class="btn btn--primary btn--xl" :to="{ name: ROUTE_NAMES.AUTH_LOGIN }">
                {{ t('actions.login') }}
              </router-link>
              <router-link
                class="btn btn--secondary btn--xl"
                :to="{ name: ROUTE_NAMES.AUTH_REGISTER }"
              >
                {{ t('nav.register') }}
              </router-link>
            </template>
            <router-link
              v-else
              class="btn btn--primary btn--xl"
              :to="{ name: ROUTE_NAMES.APP_DASHBOARD }"
            >
              {{ t('nav.dashboard') }}
            </router-link>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
