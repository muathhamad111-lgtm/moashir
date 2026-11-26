<script setup>
import { computed } from 'vue'

const props = defineProps({
  src: { type: String, default: null },
  alt: { type: String, required: true },
  variant: { type: String, default: 'default' },
})

const hasImage = computed(() => typeof props.src === 'string' && props.src.trim().length > 0)
</script>

<template>
  <figure class="illustration-slot" :class="`illustration-slot--${variant}`">
    <img
      v-if="hasImage"
      class="illustration-slot__image"
      :src="src"
      :alt="alt"
      loading="lazy"
      decoding="async"
    />
    <span v-else class="illustration-slot__placeholder" role="img" :aria-label="alt">
      <svg
        class="illustration-slot__art"
        viewBox="0 0 160 120"
        aria-hidden="true"
        focusable="false"
      >
        <defs>
          <linearGradient id="illustration-slot-fill" x1="0" y1="0" x2="1" y2="1">
            <stop class="illustration-slot__stop illustration-slot__stop--from" offset="0" />
            <stop class="illustration-slot__stop illustration-slot__stop--to" offset="1" />
          </linearGradient>
        </defs>
        <rect x="0" y="0" width="160" height="120" rx="14" fill="url(#illustration-slot-fill)" />
        <circle class="illustration-slot__accent" cx="118" cy="34" r="16" />
        <path
          class="illustration-slot__accent"
          d="M24 92 L58 60 L84 78 L120 44 L140 60"
          fill="none"
          stroke-width="5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <rect class="illustration-slot__accent-soft" x="24" y="98" width="36" height="8" rx="4" />
        <rect class="illustration-slot__accent-soft" x="68" y="98" width="24" height="8" rx="4" />
      </svg>
    </span>
  </figure>
</template>
