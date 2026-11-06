<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { usePortalIdentity } from '@/composables/usePortalIdentity'
import { usePortalSession } from '@/composables/usePortalSession'
import { useLocale } from '@/composables/useLocale'
import { useTheme } from '@/composables/useTheme'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { THEMES } from '@/constants/themes'
import AppPopover from '@/components/ui/AppPopover.vue'
import PortalMenuEntry from './PortalMenuEntry.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import LevelBadge from '@/components/level/LevelBadge.vue'
import { useEntityLevel } from '@/composables/useEntityLevel'

const { t } = useI18n()
const router = useRouter()
const identity = usePortalIdentity()
const { toggleLocale } = useLocale()
const { theme, toggleTheme } = useTheme()
const { signOut } = usePortalSession()
const popoverRef = ref(null)
const { tier, reassessment } = useEntityLevel()

const themeValue = computed(() =>
  theme.value === THEMES.dark ? t('portal.account.themeDark') : t('portal.account.themeLight'),
)

function dismiss() {
  popoverRef.value?.dismiss()
}

async function onSignOut() {
  dismiss()
  await signOut()
  router.push({ name: ROUTE_NAMES.AUTH_LOGIN })
}
</script>

<template>
  <AppPopover
    ref="popoverRef"
    class="account-menu"
    trigger-class="account-menu__chip"
    :label="t('portal.account.title')"
    :trigger-label="t('portal.account.open', { name: identity.entityName.value })"
  >
    <template #trigger="{ isOpen }">
      <span class="account-menu__avatar" aria-hidden="true">{{ identity.initial.value }}</span>
      <span class="account-menu__names">
        <span class="account-menu__entity">{{ identity.entityName.value }}</span>
        <span class="account-menu__user">{{ identity.userName.value }}</span>
      </span>
      <LevelBadge class="account-menu__level" :tier="tier" size="s" :reassessment="reassessment" />
      <AppIcon :name="isOpen ? 'chevron-up' : 'chevron-down'" class="account-menu__chevron" />
    </template>
    <template #default="{ headingId }">
      <div class="account-menu__panel">
        <h2 :id="headingId" class="account-menu__title" tabindex="-1">
          {{ t('portal.account.title') }}
        </h2>
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
          @select="dismiss"
        />
        <PortalMenuEntry
          icon="building"
          :label="t('portal.account.entityProfile')"
          :to="{ name: ROUTE_NAMES.ENTITY_PROFILE }"
          @select="dismiss"
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
          @select="dismiss"
        />
        <PortalMenuEntry
          class="account-menu__signout"
          icon="logout"
          :label="t('actions.logout')"
          tone="danger"
          @select="onSignOut"
        />
      </div>
    </template>
  </AppPopover>
</template>
