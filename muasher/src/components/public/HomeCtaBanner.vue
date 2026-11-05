<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import starMark from '@/assets/logos/moashir-star.svg'
import BaseButton from '@/components/ui/BaseButton.vue'
import BrandPattern from '@/components/ui/BrandPattern.vue'
import { isInternalPath } from '@/utils/internalPath'

const props = defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const router = useRouter()

const secondary = computed(
  () =>
    (props.section.items ?? []).find(
      (item) => String(item.meta?.role ?? '').replaceAll('_', '-') === 'secondary-cta',
    ) ?? null,
)

function go(url) {
  if (isInternalPath(url)) router.push(url)
}
</script>

<template>
  <section class="home-section home-cta" aria-labelledby="home-cta-title">
    <div class="home-section__container">
      <div v-reveal class="home-cta__banner brand-surface">
        <BrandPattern variant="lattice" />
        <img class="home-cta__star" :src="starMark" alt="" />
        <div class="home-cta__text">
          <h2 id="home-cta-title" class="home-cta__title">{{ localized(section.title) }}</h2>
          <p v-if="section.description" class="home-cta__desc">
            {{ localized(section.description) }}
          </p>
        </div>
        <div class="home-cta__actions">
          <BaseButton
            v-if="section.ctaLabel && section.ctaUrl"
            variant="primary"
            size="xl"
            icon-end="arrow-right"
            @click="go(section.ctaUrl)"
          >
            {{ localized(section.ctaLabel) }}
          </BaseButton>
          <BaseButton
            v-if="secondary?.ctaUrl"
            variant="secondary"
            size="xl"
            @click="go(secondary.ctaUrl)"
          >
            {{ localized(secondary.ctaLabel) || localized(secondary.title) }}
          </BaseButton>
        </div>
      </div>
    </div>
  </section>
</template>
