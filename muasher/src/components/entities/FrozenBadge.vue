<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'

const { t } = useI18n()
</script>

<template>
  <Badge variant="info" class="frozen-badge">
    <i class="fa-solid fa-snowflake" aria-hidden="true"></i>
    {{ t('entities.frozen.badge') }}
  </Badge>
</template>
