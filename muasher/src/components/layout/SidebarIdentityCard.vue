<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useBackOfficeLogo } from '@/composables/useBackOfficeLogo'
import { ROUTE_NAMES } from '@/constants/routeNames'

defineProps({
  homeLabel: { type: String, required: true },
  collapsed: { type: Boolean, default: false },
})

const { t } = useI18n()
const router = useRouter()
const { sidebarLogo } = useBackOfficeLogo()
const previewHref = computed(() => router.resolve({ name: ROUTE_NAMES.PUBLIC_HOME }).href)
</script>

<template>
  <div
    class="identity-card"
    :class="{ 'identity-card--collapsed': collapsed }"
    data-testid="sidebar-identity"
  >
    <router-link
      class="identity-card__brand"
      :to="{ name: ROUTE_NAMES.APP_DASHBOARD }"
      :aria-current="null"
    >
      <img class="identity-card__logo" :src="sidebarLogo" :alt="homeLabel" />
    </router-link>
    <a
      class="identity-card__preview"
      :href="previewHref"
      target="_blank"
      rel="noopener noreferrer"
      :title="collapsed ? t('shell.previewPlatform') : undefined"
      data-testid="sidebar-preview-platform"
    >
      <AppIcon name="eye" />
      <span class="identity-card__label">
        {{ t('shell.previewPlatform')
        }}<span class="identity-card__hint">{{ t('shell.opensInNewTab') }}</span>
      </span>
    </a>
  </div>
</template>
