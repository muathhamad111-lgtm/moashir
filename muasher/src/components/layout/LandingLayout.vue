<script setup>
import { computed, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePublicHome } from '@/composables/useHomeContent'
import { provideLandingReady } from '@/composables/landing/useLandingReady'
import { useRouteIntentPrefetch } from '@/composables/useRoutePrefetch'
import { hasFinePointer } from '@/utils/landing/pointer'
import LandingSprite from '@/components/landing/LandingSprite.vue'
import LandingLoader from '@/components/landing/LandingLoader.vue'
import LandingHeader from '@/components/landing/LandingHeader.vue'
import LandingFooter from '@/components/landing/LandingFooter.vue'
import LandingAssistant from '@/components/landing/LandingAssistant.vue'

const SETTLED = ['success', 'empty', 'error']

const { t } = useI18n()
const { status, sectionOfType } = usePublicHome()
const { ready, markReady } = provideLandingReady()
const settled = computed(() => SETTLED.includes(status.value))
const assistant = computed(() => sectionOfType('assistant'))
const customCursor = hasFinePointer()
const rootRef = useTemplateRef('root')

useRouteIntentPrefetch(rootRef)
</script>

<template>
  <div ref="root" class="lp" :class="{ 'is-ready': ready, 'lp-has-cursor': customCursor }">
    <LandingSprite />
    <a class="lp-skip" href="#lp-main">{{ t('landing.skipToContent') }}</a>
    <LandingLoader :done="settled" @finished="markReady" />
    <LandingHeader />
    <main id="lp-main" tabindex="-1">
      <slot />
    </main>
    <LandingFooter />
    <LandingAssistant v-if="assistant" :section="assistant" />
  </div>
</template>
