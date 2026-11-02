<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
  items: { type: Array, required: true },
})

const { t } = useI18n()
</script>

<template>
  <nav class="criteria-page__breadcrumb" :aria-label="t('criteria.breadcrumb.label')">
    <ol class="criteria-page__crumbs">
      <li v-for="(item, index) in items" :key="index" class="criteria-page__crumb-item">
        <RouterLink v-if="item.to" class="criteria-page__crumb" :to="item.to">{{
          item.label
        }}</RouterLink>
        <span v-else class="criteria-page__crumb is-current" aria-current="page">{{
          item.label
        }}</span>
        <i
          v-if="index < items.length - 1"
          class="fa-solid fa-chevron-right u-flip-in-rtl criteria-page__crumb-sep"
          aria-hidden="true"
        ></i>
      </li>
    </ol>
  </nav>
</template>
