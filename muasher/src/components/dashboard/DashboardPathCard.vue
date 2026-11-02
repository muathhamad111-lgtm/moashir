<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
  pathKey: { type: String, required: true },
  icon: { type: String, default: 'fa-solid fa-flag' },
  to: { type: Object, required: true },
})

const { t } = useI18n()
</script>

<template>
  <router-link class="dash-path" :class="`dash-path--${pathKey}`" :to="to">
    <span class="dash-path__icon" aria-hidden="true">
      <i :class="icon"></i>
    </span>
    <span class="dash-path__body">
      <span class="dash-path__title">{{ t(`dashboard.paths.${pathKey}.title`) }}</span>
      <span class="dash-path__summary">{{ t(`dashboard.paths.${pathKey}.summary`) }}</span>
    </span>
    <i class="dash-path__chevron fa-solid fa-chevron-left u-flip-in-rtl" aria-hidden="true"></i>
  </router-link>
</template>
