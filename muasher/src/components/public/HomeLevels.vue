<script setup>
import { useI18n } from 'vue-i18n'
import { useCertificateLevels } from '@/composables/useCertificateLevels'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import HomeLevelCard from '@/components/public/HomeLevelCard.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  localized: { type: Function, required: true },
})

const { t } = useI18n()
const { ranges, status, load } = useCertificateLevels()
</script>

<template>
  <section
    v-if="status !== 'empty'"
    class="home-section home-levels"
    aria-labelledby="home-levels-title"
  >
    <div class="home-section__container">
      <header v-reveal="{ stagger: true }" class="home-section__head home-section__head--center">
        <span class="home-section__eyebrow">
          <span class="home-section__eyebrow-line" aria-hidden="true"></span>
          {{ t('public.home.levels.eyebrow') }}
        </span>
        <h2 id="home-levels-title" class="home-section__title">
          {{ t('public.home.levels.title') }}
        </h2>
      </header>

      <div v-if="status === 'loading' || status === 'idle'" class="home-levels__grid">
        <Skeleton v-for="i in 4" :key="i" variant="block" />
      </div>
      <ErrorState
        v-else-if="status === 'error'"
        :message="t('public.home.levels.error')"
        @retry="load"
      />
      <ul v-else v-reveal="{ stagger: true }" class="home-levels__grid">
        <HomeLevelCard
          v-for="range in ranges"
          :key="range.level"
          :range="range"
          :localized="localized"
        />
      </ul>

      <p v-reveal class="home-levels__verification">
        <AppIcon name="qr-code" class="home-levels__verification-icon" />
        {{ t('public.home.levels.verification') }}
      </p>
    </div>
  </section>
</template>
