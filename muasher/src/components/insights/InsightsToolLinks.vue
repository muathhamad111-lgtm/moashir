<script setup>
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  links: { type: Array, required: true },
  footnote: { type: String, default: '' },
})

const { t } = useI18n()
const route = useRoute()
</script>

<template>
  <footer class="insights-links">
    <nav class="insights-links__list" :aria-label="t('insights.staff.tabsLabel')">
      <router-link
        v-for="link in links"
        :key="link.key"
        class="insights-links__link"
        :to="{ ...link.to, query: link.keepQuery ? route.query : undefined }"
      >
        {{ t(`insights.links.${link.key}`) }}
        <AppIcon name="arrow-right" />
      </router-link>
    </nav>
    <p v-if="footnote" class="insights-links__note">{{ footnote }}</p>
  </footer>
</template>
