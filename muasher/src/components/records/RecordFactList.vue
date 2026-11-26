<script setup>
// The ONE way a fact shows on a record page: the entity record's details list (label above value) in aligned
// columns. Each value comes from the caller's slot.
defineProps({
  facts: { type: Array, required: true },
  columns: { type: Number, default: 2 },
})
</script>

<template>
  <dl class="record-facts" :style="{ '--record-fact-columns': columns }">
    <div v-for="fact in facts" :key="fact.key" class="record-facts__row">
      <dt class="record-facts__term">{{ fact.label }}</dt>
      <dd class="record-facts__value"><slot :fact="fact.key" /></dd>
    </div>
  </dl>
</template>
