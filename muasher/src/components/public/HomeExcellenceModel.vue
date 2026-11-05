<script setup>
import HomeSectionHead from '@/components/public/HomeSectionHead.vue'
import CriteriaModelVisual from '@/components/public/CriteriaModelVisual.vue'

defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
})
</script>

<template>
  <section class="home-section home-model" aria-labelledby="home-model-title">
    <div class="home-section__container">
      <HomeSectionHead :section="section" :localized="localized" title-id="home-model-title" />
      <CriteriaModelVisual />
    </div>
  </section>
</template>
