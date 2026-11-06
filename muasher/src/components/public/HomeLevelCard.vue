<script setup>
import { computed } from 'vue'
import bronzeSeal from '@/assets/seals/seal-bronze.svg'
import silverSeal from '@/assets/seals/seal-silver.svg'
import goldSeal from '@/assets/seals/seal-gold.svg'
import diamondSeal from '@/assets/seals/seal-diamond.svg'
import LevelRange from '@/components/public/LevelRange.vue'

const SEALS = { bronze: bronzeSeal, silver: silverSeal, gold: goldSeal, diamond: diamondSeal }

const props = defineProps({
  range: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const seal = computed(() => SEALS[props.range.level] ?? null)
const label = computed(() => props.localized(props.range.label))
</script>

<template>
  <li class="home-level" :class="`home-level--${range.level}`">
    <span v-if="seal" class="home-level__seal">
      <img class="home-level__seal-image" :src="seal" alt="" loading="lazy" decoding="async" />
    </span>
    <h3 class="home-level__name">{{ label }}</h3>
    <LevelRange class="home-level__range" :range="range" />
  </li>
</template>
