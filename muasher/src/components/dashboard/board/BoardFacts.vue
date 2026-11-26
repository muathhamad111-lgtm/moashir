<script setup>
// A small key-value card body: label on the start side, value on the end side, one row each.
defineProps({
  rows: { type: Array, required: true },
  caption: { type: String, required: true },
})
</script>

<template>
  <dl class="board-facts" :aria-label="caption">
    <div v-for="row in rows" :key="row.key" class="board-facts__row" :data-fact="row.key">
      <dt>{{ row.label }}</dt>
      <dd :class="row.tone ? `board-facts__value--${row.tone}` : ''">
        <bdi>{{ row.value }}</bdi>
      </dd>
    </div>
  </dl>
</template>
