<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { useAuthStore } from '@/stores/auth'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  path: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const router = useRouter()
const auth = useAuthStore()

const TARGET_ROUTES = {
  self: ROUTE_NAMES.ASSESSMENT_SELF,
  accredited: ROUTE_NAMES.ASSESSMENT_ACCREDITED,
  advanced: ROUTE_NAMES.ASSESSMENT_ADVANCED,
}

const iconName = computed(() => props.path.icon || 'flag')

function start() {
  const target = TARGET_ROUTES[props.path.key]
  if (!target) return
  if (auth.isAuthenticated) {
    router.push({ name: target })
    return
  }
  router.push({ name: ROUTE_NAMES.AUTH_LOGIN, query: { redirect: props.path.key } })
}
</script>

<template>
  <article class="path-card" :class="`path-card--${path.key}`">
    <span class="path-card__icon" aria-hidden="true">
      <AppIcon :name="iconName" />
    </span>
    <h3 class="path-card__title">{{ localized(path.title) }}</h3>
    <p class="path-card__summary">{{ localized(path.summary) }}</p>
    <div class="path-card__cta">
      <BaseButton variant="primary" icon="arrow-right" @click="start">
        {{ $t(`public.paths.${path.key}.cta`) }}
      </BaseButton>
    </div>
  </article>
</template>
