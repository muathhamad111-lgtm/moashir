<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  items: { type: Array, required: true },
})

const { t } = useI18n()
</script>

<template>
  <nav class="portal-breadcrumb" :aria-label="t('portal.breadcrumb.label')">
    <ol class="portal-breadcrumb__list">
      <li v-for="item in items" :key="item.key" class="portal-breadcrumb__item">
        <router-link v-if="item.to" class="portal-breadcrumb__link" :to="item.to">{{
          item.label
        }}</router-link>
        <span v-else class="portal-breadcrumb__current" aria-current="page">
          <bdi v-if="item.isReference" dir="ltr">{{ item.label }}</bdi>
          <template v-else>{{ item.label }}</template>
        </span>
        <AppIcon v-if="item.to" name="chevron-right" class="portal-breadcrumb__separator" />
      </li>
    </ol>
  </nav>
</template>
