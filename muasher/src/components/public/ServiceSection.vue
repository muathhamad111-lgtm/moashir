<script setup>
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  icon: { type: String, default: 'star' },
  title: { type: String, required: true },
  body: { type: String, required: true },
  future: { type: Boolean, default: false },
})
</script>

<template>
  <article class="service-section" :class="{ 'service-section--future': future }">
    <span class="service-section__icon" aria-hidden="true">
      <AppIcon :name="icon" />
    </span>
    <div class="service-section__content">
      <div class="service-section__head">
        <h3 class="service-section__title">{{ title }}</h3>
        <span v-if="future" class="service-section__badge">{{ $t('common.comingSoon') }}</span>
      </div>
      <p class="service-section__body">{{ body }}</p>
    </div>
  </article>
</template>
