<script setup>
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { usePortalIdentity } from '@/composables/usePortalIdentity'
import { useFocusTrap } from '@/composables/useFocusTrap'
import { usePortalSession } from '@/composables/usePortalSession'
import { useLocale } from '@/composables/useLocale'
import { useTheme } from '@/composables/useTheme'
import { useOutsideClick } from '@/composables/useOutsideClick'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { THEMES } from '@/constants/themes'
import PortalMenuEntry from './PortalMenuEntry.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const router = useRouter()
const identity = usePortalIdentity()
const { toggleLocale } = useLocale()
const { theme, toggleTheme } = useTheme()
const { signOut } = usePortalSession()

const isOpen = ref(false)
const rootRef = ref(null)
const panelRef = ref(null)
const panelId = useId()

const themeValue = computed(() =>
  theme.value === THEMES.dark ? t('portal.account.themeDark') : t('portal.account.themeLight'),
)

function close() {
  isOpen.value = false
}

function toggle() {
  isOpen.value = !isOpen.value
}

const { onKeydown } = useFocusTrap(panelRef, { isActive: isOpen, onEscape: close })
useOutsideClick(rootRef, { isActive: isOpen, onOutside: close })

async function onSignOut() {
  close()
  await signOut()
  router.push({ name: ROUTE_NAMES.AUTH_LOGIN })
}
</script>

<template>
  <div ref="rootRef" class="account-menu">
    <button
      type="button"
      class="account-menu__chip"
      :aria-expanded="isOpen"
      :aria-controls="panelId"
      :aria-label="t('portal.account.open', { name: identity.entityName.value })"
      @click="toggle"
    >
      <span class="account-menu__avatar" aria-hidden="true">{{ identity.initial.value }}</span>
      <span class="account-menu__names">
        <span class="account-menu__entity">{{ identity.entityName.value }}</span>
        <span class="account-menu__user">{{ identity.userName.value }}</span>
      </span>
      <AppIcon name="chevron-down" class="account-menu__chevron" />
    </button>

    <div
      v-if="isOpen"
      :id="panelId"
      ref="panelRef"
      class="account-menu__panel"
      role="dialog"
      :aria-label="t('portal.account.title')"
      @keydown="onKeydown"
    >
      <div class="account-menu__header">
        <span class="account-menu__avatar account-menu__avatar--lg" aria-hidden="true">{{
          identity.initial.value
        }}</span>
        <span class="account-menu__identity">
          <span class="account-menu__identity-entity">{{ identity.entityName.value }}</span>
          <span class="account-menu__identity-user"
            >{{ identity.userName.value }} · {{ identity.roleLabel.value }}</span
          >
          <bdi class="account-menu__identity-email" dir="ltr">{{ identity.email.value }}</bdi>
        </span>
      </div>
      <PortalMenuEntry
        icon="user"
        :label="t('portal.account.profile')"
        :to="{ name: ROUTE_NAMES.ACCOUNT_PROFILE }"
        @select="close"
      />
      <PortalMenuEntry
        icon="building"
        :label="t('portal.account.entityProfile')"
        :to="{ name: ROUTE_NAMES.ENTITY_PROFILE }"
        @select="close"
      />
      <PortalMenuEntry
        icon="language"
        :label="t('portal.account.language')"
        :value="t('locale.switchLabel')"
        @select="toggleLocale"
      />
      <PortalMenuEntry
        icon="moon-fill"
        :label="t('portal.account.theme')"
        :value="themeValue"
        @select="toggleTheme"
      />
      <PortalMenuEntry
        icon="question-circle"
        :label="t('portal.account.help')"
        :to="{ name: ROUTE_NAMES.PORTAL_HELP }"
        @select="close"
      />
      <PortalMenuEntry
        class="account-menu__signout"
        icon="logout"
        :label="t('actions.logout')"
        tone="danger"
        @select="onSignOut"
      />
    </div>
  </div>
</template>
