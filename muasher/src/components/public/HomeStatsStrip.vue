<script setup>
import { useI18n } from 'vue-i18n'
import { useFeaturedKpis } from '@/composables/useFeaturedKpis'
import { HOME_STATS_MAXIMUM } from '@/constants/kpis'
import HomeStatItem from '@/components/public/HomeStatItem.vue'

defineProps({
  localized: { type: Function, required: true },
})

const { t } = useI18n()
const { featured } = useFeaturedKpis({ limit: HOME_STATS_MAXIMUM })
</script>

<template>
  <div v-if="featured.length" class="home-stats-band">
    <div class="home-stats-band__container">
      <ul
        v-reveal="{ stagger: true }"
        class="home-stats"
        :aria-label="t('public.home.stats.label')"
      >
        <HomeStatItem v-for="kpi in featured" :key="kpi.id" :kpi="kpi" :localized="localized" />
      </ul>
    </div>
  </div>
</template>
