<script setup>
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  links: { type: Array, required: true },
  isActive: { type: Function, required: true },
  variant: { type: String, default: 'bar' },
})

const emit = defineEmits(['navigate'])
</script>

<template>
  <ul class="public-nav-links" :class="`public-nav-links--${variant}`">
    <li v-for="link in links" :key="link.key">
      <a
        v-if="link.href"
        class="public-nav-links__link"
        :href="link.href"
        target="_blank"
        rel="noopener noreferrer"
      >
        <span>{{ link.label }}</span>
        <AppIcon
          v-if="variant === 'drawer'"
          name="chevron-right"
          class="public-nav-links__chevron"
        />
      </a>
      <router-link
        v-else
        class="public-nav-links__link"
        :class="{ 'is-active': isActive(link) }"
        :to="link.to"
        :aria-current="isActive(link) ? 'location' : undefined"
        @click="emit('navigate', $event, link)"
      >
        <span>{{ link.label }}</span>
        <AppIcon
          v-if="variant === 'drawer'"
          name="chevron-right"
          class="public-nav-links__chevron"
        />
      </router-link>
    </li>
  </ul>
</template>
