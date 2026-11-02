<script setup>
import { computed } from 'vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import BaseButton from '@/components/ui/BaseButton.vue'
import IllustrationSlot from '@/components/public/IllustrationSlot.vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const { t } = useI18n()
const authStore = useAuthStore()

const isAuthenticated = computed(() => authStore.isAuthenticated)

const actionLabelKey = computed(() =>
  isAuthenticated.value ? 'errors.server.actionDashboard' : 'errors.server.actionHome',
)

function goSafe() {
  router.push({
    name: isAuthenticated.value ? ROUTE_NAMES.APP_DASHBOARD : ROUTE_NAMES.PUBLIC_HOME,
  })
}

function reload() {
  router.go(0)
}
</script>

<template>
  <div class="error-page">
    <div class="error-page__inner">
      <IllustrationSlot
        class="error-page__media"
        src="/generated/error-500.jpg"
        :alt="t('illustrations.error')"
        variant="compact"
      />
      <span class="error-page__icon error-page__icon--danger" aria-hidden="true">
        <i class="fa-solid fa-server"></i>
      </span>
      <p class="error-page__code" aria-hidden="true">{{ $t('errors.server.code') }}</p>
      <h1 class="error-page__title">{{ $t('errors.server.title') }}</h1>
      <p class="error-page__message">{{ $t('errors.server.message') }}</p>

      <div class="error-page__actions">
        <BaseButton variant="primary" icon="fa-solid fa-rotate-right" @click="reload">
          {{ $t('errors.server.actionRetry') }}
        </BaseButton>
        <BaseButton
          variant="secondary"
          :icon="isAuthenticated ? 'fa-solid fa-gauge-high' : 'fa-solid fa-house'"
          @click="goSafe"
        >
          {{ $t(actionLabelKey) }}
        </BaseButton>
      </div>
    </div>
  </div>
</template>
