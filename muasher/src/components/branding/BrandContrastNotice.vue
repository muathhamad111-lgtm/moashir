<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  pairs: { type: Array, required: true },
  scheme: { type: Object, default: null },
})

const { t, n } = useI18n()

const failing = computed(() => props.pairs.filter((pair) => !pair.passes))
const isShaded = computed(
  () => Boolean(props.scheme) && props.scheme.primaryText !== props.scheme.primary,
)
</script>

<template>
  <div v-if="failing.length" class="brand-contrast brand-contrast--blocked" role="alert">
    <p class="brand-contrast__title">
      <AppIcon name="warning-triangle" />
      <span>{{ t('branding.contrast.blocked') }}</span>
    </p>
    <ul class="brand-contrast__list">
      <li v-for="pair in failing" :key="pair.key" class="brand-contrast__row">
        <span>{{ t(`branding.contrast.pairs.${pair.key}`) }}</span>
        <span class="brand-contrast__ratio u-ltr">
          {{ t('branding.contrast.ratio', { ratio: formatNumber(n, pair.ratio, 'money') }) }}
        </span>
      </li>
    </ul>
  </div>
  <p v-else-if="isShaded" class="brand-contrast brand-contrast--note" role="status">
    <AppIcon name="info-circle" />
    <span>{{ t('branding.contrast.shadeNote') }}</span>
    <bdi class="brand-contrast__hex u-ltr">{{ scheme.primaryText }}</bdi>
  </p>
</template>
