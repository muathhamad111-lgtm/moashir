<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { scrollBehavior } from '@/utils/motion'
import { useAccreditedPrice } from '@/composables/useAccreditedPrice'
import HomeSectionHead from '@/components/public/HomeSectionHead.vue'
import HomePathCard from '@/components/public/HomePathCard.vue'

const props = defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const router = useRouter()
const { priceBeforeVat } = useAccreditedPrice()
const paths = computed(() => props.section.items ?? [])

function activate(item) {
  const url = item.ctaUrl
  if (!url) return
  if (url.startsWith('#')) {
    document.getElementById(url.slice(1))?.scrollIntoView({ behavior: scrollBehavior() })
    return
  }
  if (/^https?:\/\//.test(url)) {
    window.open(url, '_blank', 'noopener')
    return
  }
  router.push(url)
}
</script>

<template>
  <section class="home-section home-paths" aria-labelledby="home-paths-title">
    <div class="home-section__container">
      <HomeSectionHead :section="section" :localized="localized" title-id="home-paths-title" />
      <div v-reveal="{ stagger: true }" class="home-paths__grid">
        <HomePathCard
          v-for="path in paths"
          :key="path.id"
          :path="path"
          :localized="localized"
          :price="priceBeforeVat"
          @activate="activate"
        />
      </div>
    </div>
  </section>
</template>
