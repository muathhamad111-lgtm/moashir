<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { usePermissions } from '@/composables/usePermissions'
import { useNavbarUserMenu } from '@/composables/useNavbarUserMenu'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES as R } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'
import PortalMenuEntry from '@/components/portal/PortalMenuEntry.vue'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermissions()
const { select } = useNavbarUserMenu({ holdsTools: true })

const canSeeEntityProfile = computed(() => can(PERMISSIONS.ENTITY_PROFILE_VIEW))
const previewHref = computed(() => router.resolve({ name: R.PUBLIC_HOME }).href)
</script>

<!-- The account part of «المزيد»: the same actions as the desktop avatar menu, plus the platform preview. -->
<template>
  <section class="portal-more__group" aria-labelledby="app-more-account">
    <h2 id="app-more-account" class="portal-more__group-title">
      {{ t('portal.more.account') }}
    </h2>
    <div class="portal-more__list">
      <PortalMenuEntry
        icon="user-circle"
        :label="t('nav.account')"
        :to="{ name: R.ACCOUNT_PROFILE }"
      />
      <PortalMenuEntry
        v-if="canSeeEntityProfile"
        icon="building"
        :label="t('nav.entityProfile')"
        :to="{ name: R.ENTITY_PROFILE }"
      />
      <PortalMenuEntry
        icon="bell"
        :label="t('nav.notifications')"
        :to="{ name: R.NOTIFICATIONS_INDEX }"
      />
      <PortalMenuEntry
        icon="globe"
        :label="t('locale.switchLabel')"
        data-testid="app-more-locale"
        @select="select('locale')"
      />
      <a
        class="portal-menu-entry portal-menu-entry--default"
        :href="previewHref"
        target="_blank"
        rel="noopener noreferrer"
      >
        <AppIcon name="eye" class="portal-menu-entry__icon" />
        <span class="portal-menu-entry__label">
          {{ t('shell.previewPlatform')
          }}<span class="app-more__hint">{{ t('shell.opensInNewTab') }}</span>
        </span>
      </a>
      <PortalMenuEntry
        icon="logout"
        tone="danger"
        :label="t('actions.logout')"
        data-testid="app-more-logout"
        @select="select('logout')"
      />
    </div>
  </section>
</template>
