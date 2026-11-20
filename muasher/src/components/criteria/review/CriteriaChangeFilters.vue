<script setup>
import { useI18n } from 'vue-i18n'
import BaseInput from '@/components/form/BaseInput.vue'
import Badge from '@/components/ui/Badge.vue'
import { CHANGE_TILES } from '@/utils/criteriaChangeView'

// The tiles (counts that filter the list when pressed) and the search of the "What changed" page.
defineProps({
  counts: { type: Object, required: true },
  tile: { type: String, default: null },
  query: { type: String, default: '' },
})

const emit = defineEmits(['update:tile', 'update:query'])
const { t } = useI18n()

// The same colours as the tags of each row.
const VARIANTS = { keepsScore: 'success', needsNewScore: 'warning', added: 'info' }
</script>

<template>
  <div class="criteria-change-filters">
    <div
      class="criteria-change-filters__tiles"
      role="group"
      :aria-label="t('criteria.changes.tilesLabel')"
    >
      <button
        v-for="name in CHANGE_TILES"
        :key="name"
        type="button"
        class="criteria-change-tile"
        :aria-pressed="tile === name"
        @click="emit('update:tile', tile === name ? null : name)"
      >
        <Badge :variant="VARIANTS[name]">{{ t(`criteria.changes.tiles.${name}`) }}</Badge>
        <bdi class="criteria-change-tile__count">{{ counts[name] }}</bdi>
      </button>
    </div>
    <BaseInput
      class="criteria-change-filters__search"
      type="search"
      name="changeSearch"
      :model-value="query"
      :label="t('criteria.changes.search')"
      :placeholder="t('criteria.changes.searchPlaceholder')"
      @update:model-value="emit('update:query', $event)"
    />
  </div>
</template>
