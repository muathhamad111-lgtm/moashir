<script setup>
import { inject } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import CycleStatusBadge from '@/components/award/CycleStatusBadge.vue'
import { localizedText } from '@/utils/localizedText'
import AppIcon from '@/components/ui/AppIcon.vue'
import { CYCLE_WORKSPACE } from '@/utils/award/cycleWorkspace'

defineProps({
  title: { type: String, required: true },
  cycle: { type: Object, default: null },
  back: { type: Object, default: null },
  backLabel: { type: String, default: '' },
})

const { locale } = useI18n()
// Inside the cycle workspace the shared header already names the cycle and leads back to the list.
const embedded = Boolean(inject(CYCLE_WORKSPACE, null))
</script>

<template>
  <header class="cycles-page__header award-page-header" :class="{ 'is-embedded': embedded }">
    <div>
      <RouterLink v-if="back && !embedded" class="cycle-editor__back" :to="back">
        <AppIcon name="arrow-left" />
        {{ backLabel }}
      </RouterLink>
      <component :is="embedded ? 'h2' : 'h1'" class="cycles-page__title">{{ title }}</component>
      <p v-if="cycle && !embedded" class="cycles-page__subtitle">
        {{ localizedText(cycle.name, locale) }} <bdi class="u-ltr">{{ cycle.year }}</bdi>
        <CycleStatusBadge :status="cycle.status" />
      </p>
    </div>
    <div v-if="$slots.default" class="award-page-header__actions"><slot /></div>
  </header>
</template>
