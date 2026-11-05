<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { usePermissions } from '@/composables/usePermissions'
import { accessFor } from '@/constants/routeAccess'

const { t } = useI18n()
const { canAccess } = usePermissions()

const sections = computed(() => [
  {
    key: 'general',
    to: ROUTE_NAMES.ADMIN_SETTINGS_GENERAL,
    icon: 'fa-solid fa-sliders',
    group: 'platform',
  },
  {
    key: 'branding',
    to: ROUTE_NAMES.ADMIN_BRANDING,
    icon: 'fa-solid fa-palette',
    group: 'platform',
  },
  {
    key: 'otp',
    to: ROUTE_NAMES.ADMIN_SETTINGS_OTP,
    icon: 'fa-solid fa-shield-halved',
    group: 'platform',
  },
  {
    key: 'assessment',
    to: ROUTE_NAMES.ADMIN_SETTINGS_ASSESSMENT,
    icon: 'fa-solid fa-list-check',
    group: 'platform',
  },
  {
    key: 'finance',
    to: ROUTE_NAMES.ADMIN_SETTINGS_FINANCE,
    icon: 'fa-solid fa-coins',
    group: 'platform',
  },
  {
    key: 'certificates',
    to: ROUTE_NAMES.ADMIN_SETTINGS_CERTIFICATES,
    icon: 'fa-solid fa-award',
    group: 'platform',
  },
  {
    key: 'notifications',
    to: ROUTE_NAMES.ADMIN_SETTINGS_NOTIFICATIONS,
    icon: 'fa-solid fa-bell',
    group: 'platform',
  },
  {
    key: 'cities',
    to: ROUTE_NAMES.ADMIN_SETTINGS_CITIES,
    icon: 'fa-solid fa-city',
    group: 'platform',
  },
  {
    key: 'integrations',
    to: ROUTE_NAMES.ADMIN_SETTINGS_INTEGRATIONS,
    icon: 'fa-solid fa-plug',
    group: 'integrations',
  },
  {
    key: 'payment',
    to: ROUTE_NAMES.ADMIN_SETTINGS_PAYMENT,
    icon: 'fa-solid fa-credit-card',
    group: 'integrations',
  },
  {
    key: 'sms',
    to: ROUTE_NAMES.ADMIN_SETTINGS_SMS,
    icon: 'fa-solid fa-comment-sms',
    group: 'integrations',
  },
  {
    key: 'email',
    to: ROUTE_NAMES.ADMIN_SETTINGS_EMAIL,
    icon: 'fa-solid fa-envelope',
    group: 'integrations',
  },
  {
    key: 'abandonedCarts',
    to: ROUTE_NAMES.ADMIN_SETTINGS_ABANDONED_CARTS,
    icon: 'fa-solid fa-cart-arrow-down',
    group: 'integrations',
  },
  {
    key: 'ai',
    to: ROUTE_NAMES.ADMIN_SETTINGS_AI,
    icon: 'fa-solid fa-robot',
    group: 'integrations',
  },
  {
    key: 'homepage',
    to: ROUTE_NAMES.ADMIN_SETTINGS_HOMEPAGE,
    icon: 'fa-solid fa-house-chimney',
    group: 'content',
  },
  {
    key: 'aiKnowledgeBase',
    to: ROUTE_NAMES.ADMIN_SETTINGS_AI_KNOWLEDGE_BASE,
    icon: 'fa-solid fa-book-open',
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
              <i :class="section.icon" aria-hidden="true"></i>
            </span>
            <span class="settings-hub__card-body">
              <span class="settings-hub__card-title">
                {{ t(`settings.hub.sections.${section.key}.title`) }}
              </span>
              <span class="settings-hub__card-desc">
                {{ t(`settings.hub.sections.${section.key}.desc`) }}
              </span>
            </span>
            <i
              class="fa-solid fa-chevron-right u-flip-in-rtl settings-hub__card-arrow"
              aria-hidden="true"
            ></i>
          </RouterLink>
        </li>
      </ul>
    </div>
  </section>
</template>
