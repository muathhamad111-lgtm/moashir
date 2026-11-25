<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useAppTabs } from '@/composables/useAppTabs'
import { groupNavItems } from '@/constants/navigation'
import AccountAvatar from '@/components/account/AccountAvatar.vue'
import Badge from '@/components/ui/Badge.vue'
import PortalMenuEntry from '@/components/portal/PortalMenuEntry.vue'
import AppMoreAccount from './AppMoreAccount.vue'

const { t, locale } = useI18n()
const auth = useAuthStore()
const { moreItems } = useAppTabs()

const groups = computed(() => groupNavItems(moreItems.value))
const displayName = computed(() => {
  const name = auth.user?.name
  const text = typeof name === 'object' && name ? (name[locale.value] ?? name.ar ?? name.en) : name
  return text || t('nav.userFallback')
})
const roleLabels = computed(() => [
  ...(auth.isSuperAdmin ? [t('nav.superAdminRole')] : []),
  ...auth.roles.map((role) => t(`users.roleLabels.${role}`)),
])
const groupTitle = (group) => t(group.labelKey ?? `shell.more.groups.${group.id}`)
const countText = (item) => (item.count ? String(item.count) : '')
</script>

<!-- «المزيد» for back-office accounts on phones: every destination the tab bar does not hold. -->
<template>
  <section class="portal-more app-more" data-testid="app-more">
    <header class="portal-more__header">
      <p class="portal-more__eyebrow">{{ t('nav.tabs.more') }}</p>
      <h1 class="portal-more__title">{{ t('shell.more.title') }}</h1>
    </header>

    <div class="portal-more__identity">
      <span class="portal-more__avatar" aria-hidden="true"><AccountAvatar /></span>
      <span class="portal-more__names">
        <span class="portal-more__entity">{{ displayName }}</span>
        <span class="app-more__roles">
          <Badge v-for="label in roleLabels" :key="label" variant="info">{{ label }}</Badge>
        </span>
      </span>
    </div>

    <section
      v-for="group in groups"
      :key="group.id"
      class="portal-more__group"
      :aria-labelledby="`app-more-${group.id}`"
    >
      <h2 :id="`app-more-${group.id}`" class="portal-more__group-title">
        {{ groupTitle(group) }}
      </h2>
      <div class="portal-more__list">
        <PortalMenuEntry
          v-for="item in group.items"
          :key="item.id"
          :icon="item.icon"
          :label="t(item.labelKey)"
          :value="countText(item)"
          :to="{ name: item.routeName }"
          :data-nav="item.id"
        />
      </div>
    </section>

    <AppMoreAccount />
  </section>
</template>
