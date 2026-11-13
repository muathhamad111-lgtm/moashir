<script setup>
defineProps({
  rows: { type: Array, required: true },
})
</script>

<template>
  <dl class="entity-details">
    <div v-for="row in rows" :key="row.key" class="entity-details__row">
      <dt class="entity-details__term">{{ row.label }}</dt>
      <dd class="entity-details__value" :dir="row.free ? 'auto' : undefined">
        <a
          v-if="row.href"
          class="entity-details__link"
          :href="row.href"
          target="_blank"
          rel="noopener noreferrer"
          ><bdi dir="ltr">{{ row.value }}</bdi></a
        >
        <bdi v-else-if="row.ltr" dir="ltr">{{ row.value }}</bdi>
        <template v-else>{{ row.value }}</template>
      </dd>
    </div>
  </dl>
</template>
