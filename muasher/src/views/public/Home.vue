<script setup>
import { computed, nextTick, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { usePublicHome } from '@/composables/useHomeContent'
import { usePageSeo } from '@/composables/usePageSeo'
import { useFeaturedKpis } from '@/composables/useFeaturedKpis'
import { useHeroFontPreload } from '@/composables/useHeroFontPreload'
import { useLandingReady } from '@/composables/landing/useLandingReady'
import { landingEntries } from '@/components/landing/landingSectionRegistry'

const route = useRoute()
const { t } = useI18n()
const { orderedSections, siteSettings, seo, status, load, localized } = usePublicHome()
const { onReady } = useLandingReady()
useFeaturedKpis()
useHeroFontPreload()

usePageSeo({
  title: () => localized(seo.value?.title) || localized(siteSettings.value?.seo?.title),
  description: () =>
    localized(seo.value?.description) || localized(siteSettings.value?.seo?.description),
})

const entries = computed(() => landingEntries(orderedSections.value))

function scrollToHash() {
  const anchor = route.hash ? route.hash.slice(1) : ''
  if (anchor) document.getElementById(anchor)?.scrollIntoView()
}

watch(
  () => [status.value, route.hash],
  ([current]) => {
    if (current !== 'success') return
    onReady(() => nextTick(scrollToHash))
  },
  { immediate: true },
)
</script>

<template>
  <div class="lp-page">
    <div v-if="status === 'error' || status === 'empty'" class="lp-state" role="status">
      <h1>
        {{ t(status === 'error' ? 'landing.state.errorTitle' : 'public.notPublished.title') }}
      </h1>
      <p>{{ t(status === 'error' ? 'public.loadError' : 'public.notPublished.message') }}</p>
      <button v-if="status === 'error'" type="button" class="lp-btn lp-btn--fill" @click="load">
        {{ t('actions.reload') }}
      </button>
    </div>
    <template v-else>
      <component
        :is="entry.view.component"
        v-for="entry in entries"
        :id="entry.anchor || undefined"
        :key="entry.key"
        :section="entry.section"
      />
    </template>
  </div>
</template>
