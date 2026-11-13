<script setup>
import { computed, nextTick, watch } from 'vue'
import { useRoute } from 'vue-router'
import { usePublicHome } from '@/composables/useHomeContent'
import { usePageSeo } from '@/composables/usePageSeo'
import { useAmbientMotion } from '@/composables/useAmbientMotion'
import { useHeroFontPreload } from '@/composables/useHeroFontPreload'
import { useFeaturedKpis } from '@/composables/useFeaturedKpis'
import { homeEntries } from '@/components/public/homeSectionRegistry'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'

const route = useRoute()
const { orderedSections, siteSettings, seo, status, load, localized } = usePublicHome()
const { paused } = useAmbientMotion()
const { status: featuredStatus } = useFeaturedKpis()

useHeroFontPreload()
usePageSeo({
  title: () => localized(seo.value?.title) || localized(siteSettings.value?.seo?.title),
  description: () =>
    localized(seo.value?.description) || localized(siteSettings.value?.seo?.description),
})

const PENDING = ['idle', 'loading']
const isLoading = computed(
  () => PENDING.includes(status.value) || PENDING.includes(featuredStatus.value),
)
const entries = computed(() => homeEntries(orderedSections.value))

function bindingsOf(entry) {
  if (!entry.section) return { localized }
  const extra = entry.view.needsSiteSettings ? { siteSettings: siteSettings.value } : {}
  return { section: entry.section, localized, ...extra }
}

function scrollToHash() {
  const anchor = route.hash ? route.hash.slice(1) : ''
  if (anchor) document.getElementById(anchor)?.scrollIntoView()
}

watch(
  () => [status.value, route.hash],
  async ([current]) => {
    if (current !== 'success') return
    await nextTick()
    scrollToHash()
  },
  { immediate: true },
)
</script>

<template>
  <div class="landing" :class="{ 'is-motion-paused': paused }">
    <div v-if="isLoading" class="landing__loading">
      <Skeleton variant="block" :count="2" />
      <div class="landing__loading-grid">
        <Skeleton v-for="i in 3" :key="i" variant="block" />
      </div>
    </div>

    <ErrorState v-else-if="status === 'error'" :message="$t('public.loadError')" @retry="load" />

    <EmptyState
      v-else-if="status === 'empty'"
      :title="$t('public.notPublished.title')"
      :message="$t('public.notPublished.message')"
      icon="doc"
    />

    <template v-else>
      <component
        :is="entry.view.component"
        v-for="entry in entries"
        :id="entry.view.anchor || undefined"
        :key="entry.key"
        v-bind="bindingsOf(entry)"
      />
    </template>
  </div>
</template>
