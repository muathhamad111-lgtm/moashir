<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useEntityPathsStore } from '@/stores/entityPaths'
import { usePathSummaries } from '@/composables/usePathSummaries'
import { ENTITY_PATH_ICONS, PATH_ORDER, PATH_ROUTES } from '@/constants/entityPaths'
import AppIcon from '@/components/ui/AppIcon.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'

const emit = defineEmits(['navigate'])

const { t } = useI18n()
const store = useEntityPathsStore()
const { paths, status } = storeToRefs(store)
const summaries = usePathSummaries(paths)
const rows = computed(() =>
  PATH_ORDER.filter((key) => summaries.value?.[key]?.state !== 'hidden').map((key) => ({
    key,
    status: summaries.value?.[key]?.status ?? '',
  })),
)

onMounted(() => store.ensureLoaded())
</script>

<template>
  <div class="paths-sheet" data-testid="paths-sheet">
    <Skeleton v-if="status === 'loading' && !paths" variant="text" :count="3" />
    <ul v-else class="paths-sheet__list">
      <li v-for="row in rows" :key="row.key">
        <router-link
          class="paths-sheet__row"
          :to="{ name: PATH_ROUTES[row.key] }"
          :data-testid="`paths-sheet-${row.key}`"
          @click="emit('navigate')"
        >
          <span class="paths-sheet__icon"><AppIcon :name="ENTITY_PATH_ICONS[row.key]" /></span>
          <span class="paths-sheet__text">
            <span class="paths-sheet__name">{{ t(`paths.names.${row.key}`) }}</span>
            <span v-if="row.status" class="paths-sheet__status">{{ row.status }}</span>
          </span>
          <AppIcon class="paths-sheet__chevron" name="chevron-right" />
        </router-link>
      </li>
    </ul>
  </div>
</template>
