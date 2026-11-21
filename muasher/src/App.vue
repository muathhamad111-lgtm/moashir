<script setup>
import { computed, defineAsyncComponent } from 'vue'
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
import AppLoadingScreen from '@/components/feedback/AppLoadingScreen.vue'
import AccountCompletionHost from '@/components/identity/completion/AccountCompletionHost.vue'
import { useLocale } from '@/composables/useLocale'
import { useTheme } from '@/composables/useTheme'
import { usePlatformIdentity } from '@/composables/usePlatformIdentity'
import { useSiteBranding } from '@/composables/useSiteBranding'
import { useBrandSchemeStyle } from '@/composables/useBrandSchemeStyle'
import { useTtsShell } from '@/composables/useTtsShell'
import { useDeferredVisibility } from '@/composables/useDeferredVisibility'
import { BRAND_THEME_COLOR } from '@/constants/branding'
import { THEMES } from '@/constants/themes'

const LAYOUTS = {
  app: AppLayout,
  portal: PortalLayout,
  public: PublicLayout,
  auth: AuthLayout,
  landing: defineAsyncComponent(() => import('@/components/layout/LandingLayout.vue')),
}

const { t } = useI18n()
const route = useRoute()
const { locale, dir } = useLocale()
const { theme } = useTheme()
const { layoutKey, isTtsShell } = useTtsShell()

// Until the first navigation resolves (the session check, the guards, the page chunk) the
// route is the router's start location: no layout is known yet, so the official waiting page
// stands in rather than the public chrome. Once the route resolves the layout mounts beneath
// it (the page starts loading its data) while the screen keeps its minimum time.
const isBooting = computed(() => route.matched.length === 0)
const bootMarkVisible = useDeferredVisibility(isBooting)
const showsBootScreen = computed(() => isBooting.value || bootMarkVisible.value)
const layout = computed(() => LAYOUTS[layoutKey.value] ?? PublicLayout)
const documentTheme = computed(() => (isTtsShell.value ? THEMES.light : theme.value))
const brandSchemeCss = useBrandSchemeStyle()
const brandStyles = computed(() =>
  isTtsShell.value && brandSchemeCss.value
    ? [{ key: 'brand-scheme', textContent: brandSchemeCss.value }]
    : [],
)

const { platformName, siteName } = usePlatformIdentity()
const { favicon } = useSiteBranding()

const DEFAULT_ICON_LINKS = [
  { rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' },
  { rel: 'icon', type: 'image/png', sizes: '32x32', href: '/favicon-32x32.png' },
  { rel: 'icon', type: 'image/png', sizes: '16x16', href: '/favicon-16x16.png' },
  { rel: 'icon', href: '/favicon.ico', sizes: 'any' },
  { rel: 'apple-touch-icon', sizes: '180x180', href: '/apple-touch-icon.png' },
]
const MANIFEST_LINK = { rel: 'manifest', href: '/site.webmanifest' }

const iconLinks = computed(() => [
  ...(favicon.value
    ? [
        { rel: 'icon', type: 'image/png', href: favicon.value },
        { rel: 'apple-touch-icon', href: favicon.value },
      ]
    : DEFAULT_ICON_LINKS),
  MANIFEST_LINK,
])

useHead({
  htmlAttrs: {
    lang: () => locale.value,
    dir: () => dir.value,
    'data-theme': () => documentTheme.value,
    'data-shell': () => (isTtsShell.value ? 'tts' : undefined),
  },
  title: () => (route.meta.titleKey ? t(route.meta.titleKey) : platformName.value),
  titleTemplate: (title) => (title ? `${title} | ${siteName.value}` : siteName.value),
  link: iconLinks,
  style: brandStyles,
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
  <component :is="layout" v-if="!isBooting">
    <router-view />
  </component>
  <AppLoadingScreen v-if="showsBootScreen" variant="screen" :shows-mark="bootMarkVisible" />
  <AppToast />
  <ConfirmModal />
  <SuccessModal />
  <AccountCompletionHost v-if="!isBooting" />
</template>
