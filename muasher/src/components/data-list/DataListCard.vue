<script setup>
// One white card for a list: its toolbar, then the table (with any notice that belongs to it), then
// the pagination. The parts are separated by hairlines; an empty footer takes no room.
</script>

<template>
  <div class="data-card">
    <div v-if="$slots.toolbar" class="data-card__toolbar"><slot name="toolbar" /></div>
    <slot />
    <div v-if="$slots.footer" class="data-card__footer"><slot name="footer" /></div>
  </div>
</template>
