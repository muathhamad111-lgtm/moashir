<script setup>
import { ROUTE_NAMES } from '@/constants/routeNames'
import BaseButton from '@/components/ui/BaseButton.vue'
import IllustrationSlot from '@/components/public/IllustrationSlot.vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

const router = useRouter()
const { t } = useI18n()

function goHome() {
  router.push({ name: ROUTE_NAMES.PUBLIC_HOME })
}
</script>

<template>
  <div class="error-page">
    <div class="error-page__inner">
      <IllustrationSlot
        class="error-page__media"
        src="/generated/error-404.jpg"
        :alt="t('illustrations.error')"
        variant="compact"
      />
      <span class="error-page__icon" aria-hidden="true">
        <AppIcon name="compass" />
      </span>
      <p class="error-page__code" aria-hidden="true">{{ $t('errors.notFound.code') }}</p>
      <h1 class="error-page__title">{{ $t('errors.notFound.title') }}</h1>
      <p class="error-page__message">{{ $t('errors.notFound.message') }}</p>

      <div class="error-page__actions">
        <BaseButton variant="primary" icon="home-smile" @click="goHome">
          {{ $t('errors.notFound.action') }}
        </BaseButton>
      </div>
    </div>
  </div>
</template>
