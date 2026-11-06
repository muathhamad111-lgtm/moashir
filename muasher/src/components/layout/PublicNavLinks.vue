<script setup>
import { useI18n } from 'vue-i18n'
import { HOME_ANCHORS, HOME_NAV_LINKS } from '@/constants/homeSections'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  isActive: { type: Function, required: true },
  variant: { type: String, default: 'bar' },
})

const emit = defineEmits(['navigate'])
const { t } = useI18n()

function targetOf(link) {
  if (link.routeName) return { name: link.routeName }
  if (link.id === HOME_ANCHORS.hero) return { name: ROUTE_NAMES.PUBLIC_HOME }
  return { name: ROUTE_NAMES.PUBLIC_HOME, hash: `#${link.id}` }
}
</script>

<template>
  <ul class="public-nav-links" :class="`public-nav-links--${variant}`">
    <li v-for="link in HOME_NAV_LINKS" :key="link.id">
      <router-link
        class="public-nav-links__link"
        :class="{ 'is-active': isActive(link) }"
        :to="targetOf(link)"
        :aria-current="isActive(link) ? 'location' : undefined"
        @click="emit('navigate', $event, link)"
      >
        <span>{{ t(link.labelKey) }}</span>
        <AppIcon
          v-if="variant === 'drawer'"
          name="chevron-right"
          class="public-nav-links__chevron"
        />
      </router-link>
    </li>
  </ul>
</template>
