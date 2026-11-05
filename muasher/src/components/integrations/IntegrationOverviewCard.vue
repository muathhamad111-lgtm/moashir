<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import IntegrationBadges from './IntegrationBadges.vue'
import LastTestLine from './LastTestLine.vue'
import { INTEGRATION_KIND_ICONS, INTEGRATION_KIND_ROUTES } from '@/constants/integrations'

const props = defineProps({
  row: { type: Object, required: true },
})

const { t } = useI18n()
const title = computed(() => t(`integrations.kinds.${props.row.kind}`))
const providerName = computed(() =>
  props.row.provider
    ? t(`integrations.providers.${props.row.provider}`)
    : t('integrations.overview.noProvider'),
)
</script>

<template>
  <article class="overview-card" :aria-labelledby="`overview-${row.kind}`">
    <header class="overview-card__header">
      <span class="overview-card__icon">
        <i :class="INTEGRATION_KIND_ICONS[row.kind]" aria-hidden="true"></i>
      </span>
      <div class="overview-card__heading">
        <h2 :id="`overview-${row.kind}`" class="overview-card__title">{{ title }}</h2>
        <p class="overview-card__provider">
          {{ t('integrations.overview.provider', { provider: providerName }) }}
        </p>
      </div>
    </header>
    <IntegrationBadges
      :source="row.source"
      :configured="row.configured"
      :active="row.active"
      :mode="row.mode"
      :last-test="row.lastTest"
    />
    <LastTestLine :last-test="row.lastTest" />
    <RouterLink class="overview-card__link" :to="{ name: INTEGRATION_KIND_ROUTES[row.kind] }">
      {{ t('integrations.overview.manage', { kind: title }) }}
      <i class="fa-solid fa-chevron-right u-flip-in-rtl" aria-hidden="true"></i>
    </RouterLink>
  </article>
</template>
