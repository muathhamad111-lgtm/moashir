<script setup>
import { provideListCard } from '@/composables/useListCard'

provideListCard()

// One white card for a list: an optional lead (a notice strip, quick filters), its toolbar, then the table (with any notice that belongs to it), then
// the pagination. The parts are separated by hairlines; an empty footer takes no room.
</script>

<template>
  <div class="data-card">
    <div v-if="$slots.lead" class="data-card__lead"><slot name="lead" /></div>
    <div v-if="$slots.toolbar" class="data-card__toolbar"><slot name="toolbar" /></div>
    <slot />
    <div v-if="$slots.footer" class="data-card__footer"><slot name="footer" /></div>
  </div>
</template>
