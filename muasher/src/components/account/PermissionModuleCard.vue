<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  module: { type: String, required: true },
  items: { type: Array, required: true },
  localized: { type: Function, required: true },
})

const { t } = useI18n()
</script>

<template>
  <section class="permission-module" :aria-labelledby="`permission-module-${module}`">
    <header class="permission-module__header">
      <h3 :id="`permission-module-${module}`" class="permission-module__title">
        {{ t(`permissions.groups.${module}`) }}
      </h3>
      <span class="permission-module__count">
        {{ t('account.permissions.count', { count: items.length }, items.length) }}
      </span>
    </header>
    <ul class="permission-module__list">
      <li v-for="item in items" :key="item.key" class="permission-module__item">
        <AppIcon name="check-circle" class="permission-module__icon" />
        <span class="permission-module__label">{{ localized(item.label) }}</span>
      </li>
    </ul>
  </section>
</template>
