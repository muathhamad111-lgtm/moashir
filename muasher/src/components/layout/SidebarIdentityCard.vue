<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useBackOfficeLogo } from '@/composables/useBackOfficeLogo'
import { useClipboard } from '@/composables/useClipboard'
import { useToast } from '@/composables/useToast'
import { ROUTE_NAMES } from '@/constants/routeNames'

const COPY_KEY = 'platform-link'

defineProps({
  homeLabel: { type: String, required: true },
  collapsed: { type: Boolean, default: false },
})

const { t } = useI18n()
const router = useRouter()
const toast = useToast()
const { copiedKey, copy } = useClipboard()
const { sidebarLogo } = useBackOfficeLogo()
const previewHref = computed(() => router.resolve({ name: ROUTE_NAMES.PUBLIC_HOME }).href)

async function copyLink() {
  await copy(COPY_KEY, new URL(previewHref.value, window.location.origin).href)
  if (copiedKey.value === COPY_KEY) toast.success(t('shell.platformLinkCopied'))
  else toast.error(t('shell.platformLinkCopyFailed'))
}
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
    <div class="identity-card__actions">
      <a
        class="identity-card__preview"
        :href="previewHref"
        target="_blank"
        rel="noopener noreferrer"
        :title="collapsed ? t('shell.previewPlatform') : undefined"
        data-testid="sidebar-preview-platform"
      >
        <AppIcon name="eye" class="identity-card__preview-icon" />
        <span class="identity-card__label">
          {{ t('shell.previewPlatform')
          }}<span class="identity-card__hint">{{ t('shell.opensInNewTab') }}</span>
        </span>
      </a>
      <button
        type="button"
        class="identity-card__copy"
        :aria-label="t('shell.copyPlatformLink')"
        :title="t('shell.copyPlatformLink')"
        data-testid="sidebar-copy-platform-link"
        @click="copyLink"
      >
        <AppIcon name="copy" />
      </button>
    </div>
  </div>
</template>
