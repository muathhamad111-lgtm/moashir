<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { PORTAL_TOOLS } from '@/constants/portal'
import AppIcon from '@/components/ui/AppIcon.vue'
import ComingSoonChip from './ComingSoonChip.vue'

const BENEFITS = 3

const props = defineProps({
  toolKey: { type: String, required: true },
})

const { t } = useI18n()
const tool = computed(() => PORTAL_TOOLS.find((item) => item.key === props.toolKey))
const base = computed(() => `portal.tools.items.${props.toolKey}`)
const benefits = computed(() =>
  Array.from({ length: BENEFITS }, (_, index) => t(`${base.value}.benefits.${index}`)),
)
</script>

<template>
  <section class="tool-teaser" aria-labelledby="tool-teaser-title" data-testid="tool-teaser">
    <header class="tool-teaser__band">
      <p class="tool-teaser__group">
        <AppIcon name="layers" />
        {{ t('portal.tools.group') }}
      </p>
      <div class="tool-teaser__heading">
        <span class="tool-teaser__icon" aria-hidden="true"><AppIcon :name="tool.icon" /></span>
        <h1 id="tool-teaser-title" class="tool-teaser__title">{{ t(`${base}.title`) }}</h1>
        <ComingSoonChip />
      </div>
      <p class="tool-teaser__lead">{{ t(`${base}.lead`) }}</p>
    </header>
    <div class="tool-teaser__body">
      <section class="tool-teaser__card" aria-labelledby="tool-teaser-benefits">
        <h2 id="tool-teaser-benefits" class="tool-teaser__card-title">
          {{ t('portal.tools.benefitsTitle') }}
        </h2>
        <ul class="tool-teaser__benefits">
          <li v-for="benefit in benefits" :key="benefit" class="tool-teaser__benefit">
            <AppIcon name="check-circle" class="tool-teaser__benefit-icon" />
            <span>{{ benefit }}</span>
          </li>
        </ul>
      </section>
      <section class="tool-teaser__card" aria-labelledby="tool-teaser-source">
        <h2 id="tool-teaser-source" class="tool-teaser__card-title">
          {{ t('portal.tools.basedOnTitle') }}
        </h2>
        <p class="tool-teaser__source">{{ t(`${base}.basedOn`) }}</p>
        <p class="tool-teaser__note">{{ t('portal.tools.note') }}</p>
      </section>
    </div>
    <router-link
      class="btn btn--secondary tool-teaser__back"
      :to="{ name: ROUTE_NAMES.APP_DASHBOARD }"
    >
      <AppIcon name="arrow-left" />
      {{ t('comingSoon.backToDashboard') }}
    </router-link>
  </section>
</template>
