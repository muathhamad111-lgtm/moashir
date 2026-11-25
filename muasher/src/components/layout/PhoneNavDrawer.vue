<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import BaseModal from '@/components/modal/BaseModal.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

// The phone navigation drawer: a menu button that slides in, from the reading start, every section the
// account may open (the slot holds the shell's own side menu, so access is the same as on desktop).
// It closes on Esc, the backdrop, the close button and any navigation, and traps focus while open.
const { t } = useI18n()
const route = useRoute()
const isOpen = ref(false)

watch(
  () => route.fullPath,
  () => {
    isOpen.value = false
  },
)
</script>

<template>
  <button
    type="button"
    class="btn btn--ghost btn--icon phone-nav__toggle"
    :aria-label="t('shell.navDrawer.open')"
    aria-haspopup="dialog"
    :aria-expanded="isOpen ? 'true' : 'false'"
    data-testid="nav-drawer-toggle"
    @click="isOpen = true"
  >
    <AppIcon name="menu" />
  </button>
  <BaseModal v-model="isOpen" :title="t('shell.navDrawer.title')" layout="drawer-start" size="sm">
    <div class="phone-nav" data-testid="nav-drawer">
      <slot />
    </div>
  </BaseModal>
</template>
