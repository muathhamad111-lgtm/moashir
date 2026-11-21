<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import CycleStatusBadge from '@/components/award/CycleStatusBadge.vue'
import { localizedText } from '@/utils/localizedText'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  title: { type: String, required: true },
  cycle: { type: Object, default: null },
  back: { type: Object, default: null },
  backLabel: { type: String, default: '' },
})

const { locale } = useI18n()
</script>

<template>
  <header class="cycles-page__header award-page-header">
    <div>
      <RouterLink v-if="back" class="cycle-editor__back" :to="back">
        <AppIcon name="arrow-left" />
        {{ backLabel }}
      </RouterLink>
      <h1 class="cycles-page__title">{{ title }}</h1>
      <p v-if="cycle" class="cycles-page__subtitle">
        {{ localizedText(cycle.name, locale) }} <bdi class="u-ltr">{{ cycle.year }}</bdi>
        <CycleStatusBadge :status="cycle.status" />
      </p>
    </div>
    <div v-if="$slots.default" class="award-page-header__actions"><slot /></div>
  </header>
</template>
