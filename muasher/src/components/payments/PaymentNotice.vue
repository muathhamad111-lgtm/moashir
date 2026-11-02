<script setup>
defineProps({
  tone: {
    type: String,
    default: 'info',
    validator: (value) => ['info', 'success', 'warning', 'danger'].includes(value),
  },
  icon: { type: String, required: true },
  title: { type: String, required: true },
  message: { type: String, default: '' },
  live: { type: Boolean, default: false },
})
</script>

<template>
  <section class="payment-notice" :class="`payment-notice--${tone}`" :role="live ? 'status' : undefined">
    <span class="payment-notice__icon" aria-hidden="true"><i :class="icon"></i></span>
    <div class="payment-notice__body">
      <h2 class="payment-notice__title">{{ title }}</h2>
      <p v-if="message" class="payment-notice__message">{{ message }}</p>
      <div v-if="$slots.default" class="payment-notice__extra"><slot /></div>
      <div v-if="$slots.actions" class="payment-notice__actions"><slot name="actions" /></div>
    </div>
  </section>
</template>
