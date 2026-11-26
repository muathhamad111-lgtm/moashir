<script setup>
defineProps({
  title: { type: String, required: true },
  rows: { type: Array, default: () => [] },
  text: { type: String, default: '' },
})
</script>

<template>
  <article class="cycle-detail-card">
    <h2 class="cycle-detail-card__title">{{ title }}</h2>
    <p v-if="text" class="cycle-detail-card__text">{{ text }}</p>
    <dl v-if="rows.length" class="cycle-detail-card__rows">
      <div
        v-for="row in rows"
        :key="row.label"
        class="cycle-detail-card__row"
        :class="{ 'is-long': row.long, 'is-missing': row.missing }"
      >
        <dt class="cycle-detail-card__label">{{ row.label }}</dt>
        <dd class="cycle-detail-card__value">
          <bdi>{{ row.value }}</bdi>
        </dd>
      </div>
    </dl>
  </article>
</template>
