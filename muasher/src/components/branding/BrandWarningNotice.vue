<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  warnings: { type: Array, required: true },
})

const { t } = useI18n()
</script>

<template>
  <ul v-if="warnings.length" class="brand-warning" role="status">
    <li v-for="warning in warnings" :key="warning.key" class="brand-warning__item">
      <AppIcon name="warning-triangle" />
      <span>
        {{
          t(`branding.warnings.${warning.key}`, {
            status: warning.status ? t(`branding.fixed.colors.${warning.status}`) : '',
          })
        }}
      </span>
    </li>
  </ul>
</template>
