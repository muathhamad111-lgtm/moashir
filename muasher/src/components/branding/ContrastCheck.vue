<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  pairs: { type: Array, required: true },
})

const { t } = useI18n()

function formatRatio(ratio) {
  return typeof ratio === 'number' ? ratio.toFixed(2) : '-'
}
</script>

<template>
  <ul class="contrast-check" :aria-label="t('branding.contrast.title')">
    <li v-for="pair in pairs" :key="pair.key" class="contrast-check__row">
      <span class="contrast-check__name">{{ t(`branding.contrast.pairs.${pair.key}`) }}</span>
      <span class="contrast-check__ratio u-ltr">
        {{ t('branding.contrast.ratio', { ratio: formatRatio(pair.ratio) }) }}
      </span>
      <Badge :variant="pair.passes ? 'success' : 'danger'">
        <AppIcon :name="pair.passes ? 'check' : 'x'" />
        {{ pair.passes ? t('branding.contrast.pass') : t('branding.contrast.fail') }}
      </Badge>
    </li>
  </ul>
</template>
