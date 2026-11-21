<script setup>
defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, default: '' },
  highlight: { type: String, default: '' },
  lede: { type: String, default: '' },
  center: { type: Boolean, default: false },
  titleId: { type: String, default: undefined },
})
</script>

<template>
  <div v-reveal class="lp-head lp-reveal" :class="{ 'lp-head--center': center }">
    <span v-if="eyebrow" class="lp-kicker">{{ eyebrow }}</span>
    <h2 :id="titleId" class="lp-head__title">
      {{ title }}
      <span v-if="highlight" class="lp-soft">{{ highlight }}</span>
    </h2>
    <p v-if="lede" class="lp-head__lede">{{ lede }}</p>
  </div>
</template>
