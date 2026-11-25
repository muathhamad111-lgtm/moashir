<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

// The columns «إدارة الأعمدة» cannot hide or move (what the row is, what can be done with it).
defineProps({
  columns: { type: Array, required: true },
})

const { t } = useI18n()
</script>

<template>
  <ul v-if="columns.length" class="column-manager__locked">
    <li v-for="column in columns" :key="column.key" class="column-manager__locked-row">
      <AppIcon name="lock" class="column-manager__lock" />
      <span class="column-manager__locked-name">
        {{ column.label || t('components.columns.actions') }}
      </span>
      <span class="badge badge--muted">{{ t('components.columns.alwaysShown') }}</span>
    </li>
  </ul>
</template>
