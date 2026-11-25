<script setup>
import { computed, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useEntityLevelHistory } from '@/composables/useEntityLevelHistory'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import LevelRibbon from '@/components/level/LevelRibbon.vue'
import { reassessmentOf, tierOfLevel } from '@/utils/levelTier'

// The entity's latest level as the green ribbon on its record; the level history lives in the
// shared assessment pages.
const props = defineProps({
  entityId: { type: [Number, String], required: true },
  openAccredited: { type: Boolean, default: false },
})

const { t } = useI18n()
const { status, current, tier, load } = useEntityLevelHistory(toRef(props, 'entityId'))
const ribbonTier = computed(() => tierOfLevel(tier.value, props.openAccredited))
const reassessment = computed(() => reassessmentOf(ribbonTier.value, props.openAccredited))
</script>

<template>
  <div class="entity-level">
    <Skeleton v-if="status === 'loading' || status === 'idle'" variant="block" />
    <ErrorState
      v-else-if="status === 'error'"
      :message="t('level.staff.loadError')"
      @retry="load"
    />
    <LevelRibbon v-else :tier="ribbonTier" :certificate="current" :reassessment="reassessment" />
  </div>
</template>
