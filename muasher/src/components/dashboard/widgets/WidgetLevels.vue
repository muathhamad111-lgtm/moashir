<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import LevelBadge from '@/components/level/LevelBadge.vue'
import { usePermissions } from '@/composables/usePermissions'
import { accessFor } from '@/constants/routeAccess'
import { LEVEL_QUERY_KEY } from '@/constants/levels'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { useChartDirection } from '@/composables/useChartDirection'
import { fillStartOf, levelBarRows } from '@/utils/dashboard/levelBars'
import { tierPalette } from '@/utils/levelTier'
import { formatNumber } from '@/utils/formatNumber'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  data: { type: [Array, Object, null], default: null },
})

const { t, n } = useI18n()
const { canAccess } = usePermissions()
const { isRtl } = useChartDirection()
const rows = computed(() => levelBarRows(props.data))
const linksToList = computed(() => canAccess(accessFor(ROUTE_NAMES.ADMIN_ENTITIES)))

function listLink(row) {
  return { name: ROUTE_NAMES.ADMIN_ENTITIES, query: { [LEVEL_QUERY_KEY]: row.key } }
}
</script>

<template>
  <div class="widget-levels">
    <ul class="widget-levels__list">
      <li
        v-for="row in rows"
        :key="row.key"
        class="widget-levels__row"
        :class="`level-tone--${tierPalette(row.tier)}`"
        :data-level="row.key"
      >
        <router-link
          v-if="linksToList && row.filterable"
          class="widget-levels__label widget-levels__label--link"
          :to="listLink(row)"
          :aria-label="t('dashboard.levels.openList', { level: t(`level.short.${row.tier}`) })"
        >
          <LevelBadge :tier="row.tier" size="s" />
        </router-link>
        <span v-else class="widget-levels__label"><LevelBadge :tier="row.tier" size="s" /></span>
        <svg class="widget-levels__track" focusable="false" aria-hidden="true">
          <rect
            class="widget-levels__fill"
            :x="fillStartOf(row.ratio, isRtl)"
            :width="`${row.ratio}%`"
            height="100%"
          />
        </svg>
        <bdi class="widget-levels__value" dir="ltr">{{ formatNumber(n, row.count) }}</bdi>
      </li>
    </ul>
    <p v-if="linksToList" class="widget-levels__hint">{{ t('dashboard.levels.hint') }}</p>
  </div>
</template>
