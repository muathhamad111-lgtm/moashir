<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatPadded } from '@/utils/formatNumber'
import BrandPattern from '@/components/ui/BrandPattern.vue'
import HomeSectionHead from '@/components/public/HomeSectionHead.vue'

const props = defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const { n, t } = useI18n()
const stages = computed(() => props.section.items ?? [])

function marker(index) {
  return formatPadded(n, index + 1)
}
</script>

<template>
  <section class="home-section home-journey brand-surface" aria-labelledby="home-journey-title">
    <BrandPattern variant="stars" />
    <div class="home-section__container">
      <HomeSectionHead :section="section" :localized="localized" title-id="home-journey-title" />
      <div v-reveal class="home-journey__track">
        <span class="home-journey__line" aria-hidden="true">
          <span class="home-journey__line-fill"></span>
        </span>
        <ol class="home-journey__stages" :aria-label="t('public.home.journey.stagesLabel')">
          <li v-for="(stage, index) in stages" :key="stage.id" class="home-journey__stage">
            <bdi class="home-journey__marker" dir="ltr" aria-hidden="true">{{ marker(index) }}</bdi>
            <span class="home-journey__text">
              <h3 class="home-journey__title">{{ localized(stage.title) }}</h3>
              <span v-if="stage.description" class="home-journey__desc">
                {{ localized(stage.description) }}
              </span>
            </span>
          </li>
        </ol>
      </div>
    </div>
  </section>
</template>
