<script setup>
import { computed } from 'vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import BaseButton from '@/components/ui/BaseButton.vue'
import IllustrationSlot from '@/components/public/IllustrationSlot.vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import AppIcon from '@/components/ui/AppIcon.vue'

const router = useRouter()
const { t } = useI18n()
const authStore = useAuthStore()

const isAuthenticated = computed(() => authStore.isAuthenticated)

const actionLabelKey = computed(() =>
  isAuthenticated.value ? 'errors.forbidden.actionDashboard' : 'errors.forbidden.actionHome',
)

function goSafe() {
  router.push({
    name: isAuthenticated.value ? ROUTE_NAMES.APP_DASHBOARD : ROUTE_NAMES.PUBLIC_HOME,
  })
}
</script>

<template>
  <div class="error-page">
    <div class="error-page__inner">
      <IllustrationSlot
        class="error-page__media"
        src="/generated/error-403.jpg"
        :alt="t('illustrations.error')"
        variant="compact"
      />
      <span class="error-page__icon error-page__icon--danger" aria-hidden="true">
        <AppIcon name="lock" />
      </span>
      <p class="error-page__code" aria-hidden="true">{{ $t('errors.forbidden.code') }}</p>
      <h1 class="error-page__title">{{ $t('errors.forbidden.title') }}</h1>
      <p class="error-page__message">{{ $t('errors.forbidden.message') }}</p>

      <div class="error-page__actions">
        <BaseButton
          variant="primary"
          :icon="isAuthenticated ? 'gauge' : 'home-smile'"
          @click="goSafe"
        >
          {{ $t(actionLabelKey) }}
        </BaseButton>
      </div>
    </div>
  </div>
</template>
