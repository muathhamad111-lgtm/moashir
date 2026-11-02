<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { ROUTE_NAMES } from '@/constants/routeNames'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'

defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  status: {
    type: String,
    default: 'success',
    validator: (value) => ['loading', 'error', 'forbidden', 'success'].includes(value),
  },
  errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t } = useI18n()
</script>

<template>
  <section class="settings-section">
    <header class="settings-section__header">
      <RouterLink :to="{ name: ROUTE_NAMES.ADMIN_SETTINGS }" class="settings-section__back">
        <i class="fa-solid fa-arrow-right-long" aria-hidden="true"></i>
        {{ t('settings.hub.backToHub') }}
      </RouterLink>
      <h1 class="settings-section__title">{{ title }}</h1>
      <p v-if="description" class="settings-section__desc">{{ description }}</p>
    </header>

    <div v-if="status === 'loading'" class="settings-section__loading">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="3" />
      <Skeleton variant="block" />
    </div>

    <EmptyState
      v-else-if="status === 'forbidden'"
      icon="fa-solid fa-lock"
      :title="t('integrations.forbidden.title')"
      :message="t('integrations.forbidden.message')"
    />

    <ErrorState
      v-else-if="status === 'error'"
      :message="errorMessage"
      @retry="emit('retry')"
    />

    <template v-else>
      <div class="settings-section__body">
        <slot />
      </div>
      <footer v-if="$slots.actions" class="settings-section__footer">
        <slot name="actions" />
      </footer>
    </template>
  </section>
</template>
