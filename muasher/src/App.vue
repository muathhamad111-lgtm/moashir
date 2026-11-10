<script setup>
import { computed } from 'vue'
import { useHead } from '@unhead/vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import AppToast from '@/components/feedback/AppToast.vue'
import ConfirmModal from '@/components/modal/ConfirmModal.vue'
import SuccessModal from '@/components/modal/SuccessModal.vue'
import AppLayout from '@/components/layout/AppLayout.vue'
import PublicLayout from '@/components/layout/PublicLayout.vue'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import PortalLayout from '@/components/layout/PortalLayout.vue'
import { useLocale } from '@/composables/useLocale'
import { useTheme } from '@/composables/useTheme'
import { useBrandingStore } from '@/stores/branding'
import { useAuthStore } from '@/stores/auth'
import { layoutKeyFor } from '@/utils/portalAudience'
import { BRAND_THEME_COLOR } from '@/constants/branding'

const LAYOUTS = { app: AppLayout, portal: PortalLayout, public: PublicLayout, auth: AuthLayout }

const { t } = useI18n()
const route = useRoute()
const { locale, dir } = useLocale()
const { theme } = useTheme()
const branding = useBrandingStore()
const auth = useAuthStore()

const layoutKey = computed(() => layoutKeyFor(route.meta.layout, auth.roles))
const layout = computed(() => LAYOUTS[layoutKey.value] ?? PublicLayout)
const backOfficeAccent = computed(() =>
  layoutKey.value === 'app' ? branding.accentToken : undefined,
)

useHead({
  htmlAttrs: {
    lang: () => locale.value,
    dir: () => dir.value,
    'data-theme': () => theme.value,
    'data-accent': () => backOfficeAccent.value,
  },
  title: () => (route.meta.titleKey ? t(route.meta.titleKey) : t('app.name')),
  titleTemplate: (title) => (title ? `${title} | ${t('meta.siteName')}` : t('meta.siteName')),
  link: [
    { rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' },
    { rel: 'icon', type: 'image/png', sizes: '32x32', href: '/favicon-32x32.png' },
    { rel: 'icon', type: 'image/png', sizes: '16x16', href: '/favicon-16x16.png' },
    { rel: 'icon', href: '/favicon.ico', sizes: 'any' },
    { rel: 'apple-touch-icon', sizes: '180x180', href: '/apple-touch-icon.png' },
    { rel: 'manifest', href: '/site.webmanifest' },
  ],
  meta: [
    { name: 'description', content: () => t('meta.description') },
    { name: 'theme-color', content: BRAND_THEME_COLOR },
    { name: 'msapplication-TileColor', content: BRAND_THEME_COLOR },
    { name: 'msapplication-config', content: '/browserconfig.xml' },
    { property: 'og:type', content: 'website' },
    { property: 'og:site_name', content: () => t('meta.siteName') },
    { property: 'og:title', content: () => t('meta.siteName') },
    { property: 'og:description', content: () => t('meta.description') },
    { property: 'og:image', content: '/og-image.png' },
    { property: 'og:image:width', content: '1200' },
    { property: 'og:image:height', content: '630' },
    { name: 'twitter:card', content: 'summary_large_image' },
    { name: 'twitter:title', content: () => t('meta.siteName') },
    { name: 'twitter:description', content: () => t('meta.description') },
    { name: 'twitter:image', content: '/twitter-card.png' },
  ],
})
</script>

<template>
  <component :is="layout">
    <router-view />
  </component>
  <AppToast />
  <ConfirmModal />
  <SuccessModal />
</template>
