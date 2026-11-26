<script setup>
// One row of the dashboard: its cards share the width by their weights (`--board-span`) and the
// height of the tallest one. A card that is hidden (permission, no data) gives its room to the
// others, so every row ends full and no empty cell is ever left at the bottom.
defineProps({
  label: { type: String, default: '' },
})
</script>

<template>
  <div class="board-row" role="presentation" :data-row="label || undefined">
    <slot />
  </div>
</template>
