<script setup>
import { usePublicHome } from '@/composables/useHomeContent'
import HomeHero from '@/components/public/HomeHero.vue'
import HomeEvaluationPaths from '@/components/public/HomeEvaluationPaths.vue'
import HomeExcellenceModel from '@/components/public/HomeExcellenceModel.vue'
import HomeHowItWorks from '@/components/public/HomeHowItWorks.vue'
import HomeFastKit from '@/components/public/HomeFastKit.vue'
import HomeLeadership from '@/components/public/HomeLeadership.vue'
import HomeLensesBrief from '@/components/public/HomeLensesBrief.vue'
import HomeKpiPromo from '@/components/public/HomeKpiPromo.vue'
import HomePartners from '@/components/public/HomePartners.vue'
import HomeContact from '@/components/public/HomeContact.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'

const registry = {
  hero: HomeHero,
  'evaluation-paths': HomeEvaluationPaths,
  'excellence-model': HomeExcellenceModel,
  'how-it-works': HomeHowItWorks,
  'fast-excellence-kit': HomeFastKit,
  leadership: HomeLeadership,
  'analytics-lenses': HomeLensesBrief,
  'kpi-promo': HomeKpiPromo,
  partners: HomePartners,
  contact: HomeContact,
}

const { orderedSections, siteSettings, status, load, localized } = usePublicHome()
</script>

<template>
  <div class="landing">
    <div v-if="status === 'loading'" class="landing__loading">
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
      icon="fa-regular fa-newspaper"
    />

    <template v-else>
      <template v-for="section in orderedSections" :key="section.id">
        <component
          :is="registry[section.key]"
          v-if="registry[section.key]"
          v-reveal
          :id="section.key"
          :section="section"
          :localized="localized"
          v-bind="section.key === 'contact' ? { siteSettings } : {}"
        />
      </template>
    </template>
  </div>
</template>
