<script setup>
import { computed, inject, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ADDITIONAL_ACTIONS_KEY } from '@/constants/dashboardInjection'
import ActionCenter from '@/components/dashboard/ActionCenter.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'

const props = defineProps({
  status: { type: String, required: true },
  actions: { type: Array, default: () => [] },
  errorMessage: { type: String, default: '' },
  localized: { type: Function, required: true },
})

const emit = defineEmits(['retry'])
const { t } = useI18n()
const additionalActions = inject(ADDITIONAL_ACTIONS_KEY, ref([]))
const allActions = computed(() => [...props.actions, ...additionalActions.value])
</script>

<template>
  <div class="role-dashboard">
    <ActionCenter
      :actions="allActions"
      :loading="status === 'loading'"
      :localized="localized"
    />

    <div v-if="status === 'loading'" class="role-dashboard__loading">
      <div class="role-dashboard__loading-grid">
        <Skeleton v-for="i in 4" :key="`s-${i}`" variant="block" />
      </div>
      <Skeleton variant="block" :count="2" />
    </div>

    <ErrorState
      v-else-if="status === 'error'"
      :message="errorMessage || t('dashboard.loadError')"
      @retry="emit('retry')"
    />

    <template v-else>
      <div v-reveal="{ stagger: true }" class="role-dashboard__reveal">
        <slot />
      </div>
    </template>
  </div>
</template>
