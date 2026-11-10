<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ENTITY_PATH_ICONS, PATH_ROUTES } from '@/constants/entityPaths'
import AppIcon from '@/components/ui/AppIcon.vue'
import PathPrice from './PathPrice.vue'

const props = defineProps({
  pathKey: { type: String, required: true },
  path: { type: Object, default: null },
  summary: { type: Object, required: true },
})

const { t } = useI18n()
const titleId = computed(() => `path-tile-${props.pathKey}`)
const tagKey = computed(() => `paths.tile.tags.${props.pathKey}`)
const cadence = computed(() => props.path?.cadence ?? null)
</script>

<template>
  <article
    class="path-tile"
    :class="`path-tile--${pathKey}`"
    :aria-labelledby="titleId"
    :data-testid="`path-tile-${pathKey}`"
    :data-state="summary.state"
  >
    <header class="path-tile__head">
      <span class="path-tile__icon"><AppIcon :name="ENTITY_PATH_ICONS[pathKey]" /></span>
      <h3 :id="titleId" class="path-tile__title">{{ t(`paths.names.${pathKey}`) }}</h3>
    </header>
    <p class="path-tile__tag">
      {{ cadence ? t(`paths.cadence.${cadence}`) : t(tagKey) }}
    </p>
    <PathPrice class="path-tile__price" :pricing="path?.pricing ?? null" />
    <p v-for="line in summary.lines" :key="line" class="path-tile__line">{{ line }}</p>
    <p class="path-tile__status" data-testid="path-tile-status">{{ summary.status }}</p>
    <p v-if="summary.kit" class="path-tile__kit">
      <AppIcon name="layers" />
      <span>{{ summary.kit }}</span>
    </p>
    <router-link
      class="btn btn--secondary path-tile__action"
      :to="{ name: PATH_ROUTES[pathKey] }"
      :data-testid="`path-tile-action-${pathKey}`"
    >
      <span>{{ t(`paths.tile.actions.${pathKey}.${summary.state}`) }}</span>
      <AppIcon name="arrow-right" />
    </router-link>
  </article>
</template>
