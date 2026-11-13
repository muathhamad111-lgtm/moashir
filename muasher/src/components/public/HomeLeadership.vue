<script setup>
import { useI18n } from 'vue-i18n'
import HomePill from '@/components/public/HomePill.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const { t } = useI18n()
</script>

<template>
  <section class="home-section home-leadership" aria-labelledby="home-leaders-title">
    <div class="home-section__container">
      <article v-reveal class="home-leadership__card">
        <span class="home-leadership__icon" aria-hidden="true">
          <AppIcon name="users-two" />
        </span>
        <div class="home-leadership__body">
          <h2 id="home-leaders-title" class="home-leadership__title">
            {{ localized(section.title) }}
          </h2>
          <p v-if="section.description" class="home-leadership__desc">
            {{ localized(section.description) }}
          </p>
        </div>
        <HomePill class="home-leadership__badge" tone="soon">
          {{ t('public.home.comingSoon') }}
        </HomePill>
      </article>
    </div>
  </section>
</template>
