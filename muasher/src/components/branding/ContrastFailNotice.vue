<script setup>
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  pairs: { type: Array, required: true },
})

const { t, n } = useI18n()
</script>

<template>
  <div v-if="pairs.length" class="contrast-fail" role="alert">
    <p class="contrast-fail__title">
      <AppIcon name="warning-triangle" />
      {{ t('branding.contrastFail.title') }}
    </p>
    <p class="contrast-fail__message">{{ t('branding.contrastFail.message') }}</p>
    <ul class="contrast-fail__list">
      <li v-for="pair in pairs" :key="pair.key" class="contrast-fail__row">
        <span class="contrast-fail__name">{{ t(`branding.contrast.pairs.${pair.key}`) }}</span>
        <span class="contrast-fail__ratio u-ltr">
          {{
            t('branding.contrastFail.ratio', {
              ratio: formatNumber(n, pair.ratio, 'money'),
              minimum: formatNumber(n, pair.minimum, 'money'),
            })
          }}
        </span>
      </li>
    </ul>
  </div>
</template>
