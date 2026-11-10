<script setup>
import { computed, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { PATH_ORDER } from '@/constants/entityPaths'
import { usePathSummaries } from '@/composables/usePathSummaries'
import PathTile from './PathTile.vue'

const props = defineProps({
  paths: { type: Object, default: null },
})

const { t } = useI18n()
const summaries = usePathSummaries(toRef(props, 'paths'))
const visible = computed(() =>
  summaries.value ? PATH_ORDER.filter((key) => summaries.value[key].state !== 'hidden') : [],
)
</script>

<template>
  <section
    v-if="visible.length"
    class="path-tiles"
    aria-labelledby="path-tiles-title"
    data-testid="path-tiles"
  >
    <header class="path-tiles__head">
      <h2 id="path-tiles-title" class="path-tiles__title">{{ t('paths.tile.title') }}</h2>
      <p class="path-tiles__lead">{{ t('paths.tile.lead') }}</p>
    </header>
    <div class="path-tiles__grid">
      <PathTile
        v-for="key in visible"
        :key="key"
        :path-key="key"
        :path="paths[key]"
        :summary="summaries[key]"
      />
    </div>
  </section>
</template>
