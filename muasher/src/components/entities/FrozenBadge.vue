<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
</script>

<template>
  <Badge variant="info" class="frozen-badge">
    <AppIcon name="snowflake" />
    {{ t('entities.frozen.badge') }}
  </Badge>
</template>
