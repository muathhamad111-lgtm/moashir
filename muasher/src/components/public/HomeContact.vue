<script setup>
import HomeSectionHead from '@/components/public/HomeSectionHead.vue'
import HomeContactInfo from '@/components/public/HomeContactInfo.vue'
import HomeContactForm from '@/components/public/HomeContactForm.vue'

defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
  siteSettings: { type: Object, default: null },
})
</script>

<template>
  <section class="home-section home-contact" aria-labelledby="home-contact-title">
    <div class="home-section__container">
      <HomeSectionHead :section="section" :localized="localized" title-id="home-contact-title" />
      <div class="home-contact__grid">
        <HomeContactInfo :site-settings="siteSettings" :localized="localized" />
        <div v-reveal class="home-contact__card">
          <HomeContactForm />
        </div>
      </div>
    </div>
  </section>
</template>
