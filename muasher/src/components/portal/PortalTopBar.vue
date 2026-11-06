<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useActionCenterStore } from '@/stores/actionCenter'
import { formatRiyadhDateTime } from '@/utils/datetime'
import { useBrandLogo } from '@/composables/useBrandLogo'
import { ROUTE_NAMES } from '@/constants/routeNames'
import PortalBell from './PortalBell.vue'
import AccountMenu from './AccountMenu.vue'

const { t, locale } = useI18n()
const { themedLogo } = useBrandLogo()
const { generatedAt } = storeToRefs(useActionCenterStore())
const updatedText = computed(() =>
  generatedAt.value ? formatRiyadhDateTime(generatedAt.value, locale.value) : '',
)
</script>

<template>
  <header class="portal-top-bar">
    <div class="portal-top-bar__inner">
      <router-link class="portal-top-bar__brand" :to="{ name: ROUTE_NAMES.APP_DASHBOARD }">
        <img class="portal-top-bar__logo" :src="themedLogo" :alt="t('portal.brandHome')" />
      </router-link>
      <div class="portal-top-bar__start">
        <p class="portal-top-bar__area">{{ t('portal.topBar.area') }}</p>
        <p v-if="updatedText" class="portal-top-bar__updated" data-testid="portal-updated">
          {{ t('portal.topBar.updated') }}
          <bdi>{{ updatedText }}</bdi>
        </p>
      </div>
      <div class="portal-top-bar__end">
        <PortalBell />
        <AccountMenu class="portal-top-bar__account" />
      </div>
    </div>
  </header>
</template>
