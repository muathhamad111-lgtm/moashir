<script setup>
import { useI18n } from 'vue-i18n'
import { localizedText } from '@/utils/localizedText'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  regions: { type: Array, required: true },
})

const { t, locale } = useI18n()
</script>

<template>
  <div class="cycle-regions">
    <p class="cycle-regions__label">{{ t('cycles.regionsCovered') }}</p>
    <ul class="cycle-regions__list">
      <li v-for="region in regions" :key="region.id" class="cycle-regions__item">
        <AppIcon name="location" />
        {{ localizedText(region.name, locale) }}
      </li>
    </ul>
  </div>
</template>
