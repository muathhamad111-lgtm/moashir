<script setup>
// Who the record is: its mark, its kind as a small line, its number as the page title and what it belongs to.
defineProps({
  kind: { type: String, default: '' },
  title: { type: String, required: true },
  titleIsCode: { type: Boolean, default: false },
})
</script>

<template>
  <div class="record-identity">
    <div class="record-identity__mark"><slot name="mark" /></div>
    <div class="record-identity__text">
      <p v-if="kind" class="record-identity__kind">{{ kind }}</p>
      <h1 class="record-identity__title">
        <bdi v-if="titleIsCode" dir="ltr">{{ title }}</bdi>
        <template v-else>{{ title }}</template>
      </h1>
      <p v-if="$slots.subject" class="record-identity__subject"><slot name="subject" /></p>
    </div>
  </div>
</template>
