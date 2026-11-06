<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { usePermissions } from '@/composables/usePermissions'
import { accessFor } from '@/constants/routeAccess'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const { canAccess } = usePermissions()

const sections = computed(() => [
  {
    key: 'general',
    to: ROUTE_NAMES.ADMIN_SETTINGS_GENERAL,
    icon: 'gear',
    group: 'platform',
  },
  {
    key: 'branding',
    to: ROUTE_NAMES.ADMIN_BRANDING,
    icon: 'palette',
    group: 'platform',
  },
  {
    key: 'signIn',
    to: ROUTE_NAMES.ADMIN_SETTINGS_SIGN_IN,
    icon: 'shield-info',
    group: 'platform',
  },
  {
    key: 'assessment',
    to: ROUTE_NAMES.ADMIN_SETTINGS_ASSESSMENT,
    icon: 'list-check',
    group: 'platform',
  },
  {
    key: 'finance',
    to: ROUTE_NAMES.ADMIN_SETTINGS_FINANCE,
    icon: 'sar',
    group: 'platform',
  },
  {
    key: 'certificates',
    to: ROUTE_NAMES.ADMIN_SETTINGS_CERTIFICATES,
    icon: 'award',
    group: 'platform',
  },
  {
    key: 'notifications',
    to: ROUTE_NAMES.ADMIN_SETTINGS_NOTIFICATIONS,
    icon: 'bell',
    group: 'platform',
  },
  {
    key: 'contactMessages',
    to: ROUTE_NAMES.ADMIN_SETTINGS_CONTACT_MESSAGES,
    icon: 'envelope',
    group: 'platform',
  },
  {
    key: 'cities',
    to: ROUTE_NAMES.ADMIN_SETTINGS_CITIES,
    icon: 'building',
    group: 'platform',
  },
  {
    key: 'integrations',
    to: ROUTE_NAMES.ADMIN_SETTINGS_INTEGRATIONS,
    icon: 'link',
    group: 'integrations',
  },
  {
    key: 'payment',
    to: ROUTE_NAMES.ADMIN_SETTINGS_PAYMENT,
    icon: 'credit-card',
    group: 'integrations',
  },
  {
    key: 'sms',
    to: ROUTE_NAMES.ADMIN_SETTINGS_SMS,
    icon: 'chat',
    group: 'integrations',
  },
  {
    key: 'email',
    to: ROUTE_NAMES.ADMIN_SETTINGS_EMAIL,
    icon: 'envelope',
    group: 'integrations',
  },
  {
    key: 'abandonedCarts',
    to: ROUTE_NAMES.ADMIN_SETTINGS_ABANDONED_CARTS,
    icon: 'shopping-bag',
    group: 'integrations',
  },
  {
    key: 'ai',
    to: ROUTE_NAMES.ADMIN_SETTINGS_AI,
    icon: 'robot',
    group: 'integrations',
  },
  {
    key: 'homepage',
    to: ROUTE_NAMES.ADMIN_SETTINGS_HOMEPAGE,
    icon: 'home-smile',
    group: 'content',
  },
  {
    key: 'maturityTexts',
    to: ROUTE_NAMES.ADMIN_SETTINGS_MATURITY_TEXTS,
    icon: 'book-open',
    group: 'content',
  },
  {
    key: 'aiKnowledgeBase',
    to: ROUTE_NAMES.ADMIN_SETTINGS_AI_KNOWLEDGE_BASE,
    icon: 'book-open',
    group: 'content',
  },
])

const accessibleSections = computed(() =>
  sections.value.filter((section) => canAccess(accessFor(section.to))),
)

const groups = computed(() =>
  [
    { key: 'platform', title: t('settings.hub.groups.platform') },
    { key: 'integrations', title: t('settings.hub.groups.integrations') },
    { key: 'content', title: t('settings.hub.groups.content') },
  ]
    .map((group) => ({
      ...group,
      items: accessibleSections.value.filter((section) => section.group === group.key),
    }))
    .filter((group) => group.items.length > 0),
)
</script>

<template>
  <section class="settings-hub">
    <header class="settings-hub__header">
      <h1 class="settings-hub__title">{{ t('settings.hub.title') }}</h1>
      <p class="settings-hub__subtitle">{{ t('settings.hub.subtitle') }}</p>
    </header>

    <div v-for="group in groups" :key="group.key" class="settings-hub__group">
      <h2 class="settings-hub__group-title">{{ group.title }}</h2>
      <ul class="settings-hub__grid">
        <li v-for="section in group.items" :key="section.key">
          <RouterLink :to="{ name: section.to }" class="settings-hub__card">
            <span class="settings-hub__card-icon">
              <AppIcon :name="section.icon" />
            </span>
            <span class="settings-hub__card-body">
              <span class="settings-hub__card-title">
                {{ t(`settings.hub.sections.${section.key}.title`) }}
              </span>
              <span class="settings-hub__card-desc">
                {{ t(`settings.hub.sections.${section.key}.desc`) }}
              </span>
            </span>
            <AppIcon name="chevron-right" class="settings-hub__card-arrow" />
          </RouterLink>
        </li>
      </ul>
    </div>
  </section>
</template>
