<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { usePortalIdentity } from '@/composables/usePortalIdentity'
import { usePortalSession } from '@/composables/usePortalSession'
import { useLocale } from '@/composables/useLocale'
import { useTheme } from '@/composables/useTheme'
import { usePortalCountsStore } from '@/stores/portalCounts'
import { useEntityStore } from '@/stores/entity'
import { formatNumber } from '@/utils/formatNumber'
import { percentText } from '@/utils/percentText'
import { ROUTE_NAMES as R } from '@/constants/routeNames'
import { THEMES } from '@/constants/themes'
import PortalMenuEntry from '@/components/portal/PortalMenuEntry.vue'

const { t, n } = useI18n()
const router = useRouter()
const identity = usePortalIdentity()
const { signOut } = usePortalSession()
const { toggleLocale } = useLocale()
const { theme, toggleTheme } = useTheme()
const counts = usePortalCountsStore()
const { unreadNotifications, unreadReplies } = storeToRefs(counts)
const entityStore = useEntityStore()

const profilePercent = computed(() => entityStore.profile?.profileCompletion?.percent ?? null)
const repliesValue = computed(() =>
  unreadReplies.value
    ? t(
        'portal.more.newReplies',
        { count: formatNumber(n, unreadReplies.value) },
        unreadReplies.value,
      )
    : '',
)
const notificationsValue = computed(() =>
  unreadNotifications.value ? formatNumber(n, unreadNotifications.value) : '',
)
const profileValue = computed(() =>
  profilePercent.value === null ? '' : percentText(n, t, profilePercent.value),
)
const themeValue = computed(() =>
  theme.value === THEMES.dark ? t('portal.account.themeDark') : t('portal.account.themeLight'),
)

async function onSignOut() {
  await signOut()
  router.push({ name: R.AUTH_LOGIN })
}

onMounted(() => entityStore.loadProfile().catch(() => null))
</script>

<template>
  <section class="portal-more">
    <header class="portal-more__header">
      <p class="portal-more__eyebrow">{{ t('portal.more.eyebrow') }}</p>
      <h1 class="portal-more__title">{{ t('portal.more.title') }}</h1>
    </header>

    <div class="portal-more__identity">
      <span class="portal-more__avatar" aria-hidden="true">{{ identity.initial.value }}</span>
      <span class="portal-more__names">
        <span class="portal-more__entity">{{ identity.entityName.value }}</span>
        <span class="portal-more__user"
          >{{ identity.userName.value }} · {{ identity.roleLabel.value }}</span
        >
      </span>
    </div>

    <section class="portal-more__group" aria-labelledby="portal-more-services">
      <h2 id="portal-more-services" class="portal-more__group-title">
        {{ t('portal.more.services') }}
      </h2>
      <div class="portal-more__list">
        <PortalMenuEntry
          icon="envelope"
          :label="t('portal.nav.messages')"
          :value="repliesValue"
          :to="{ name: R.MESSAGES_INDEX }"
        />
        <PortalMenuEntry
          icon="credit-card"
          :label="t('portal.nav.payments')"
          :to="{ name: R.PAYMENTS_MINE }"
        />
        <PortalMenuEntry
          icon="bell"
          :label="t('portal.more.notifications')"
          :value="notificationsValue"
          :to="{ name: R.NOTIFICATIONS_INDEX }"
        />
        <PortalMenuEntry
          icon="building"
          :label="t('portal.account.entityProfile')"
          :value="profileValue"
          :to="{ name: R.ENTITY_PROFILE }"
        />
      </div>
    </section>

    <section class="portal-more__group" aria-labelledby="portal-more-account">
      <h2 id="portal-more-account" class="portal-more__group-title">
        {{ t('portal.more.account') }}
      </h2>
      <div class="portal-more__list">
        <PortalMenuEntry
          icon="user"
          :label="t('portal.account.profile')"
          :to="{ name: R.ACCOUNT_PROFILE }"
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
          :to="{ name: R.PORTAL_HELP }"
        />
      </div>
    </section>

    <div class="portal-more__list">
      <PortalMenuEntry
        icon="logout"
        :label="t('actions.logout')"
        tone="danger"
        @select="onSignOut"
      />
    </div>
  </section>
</template>
