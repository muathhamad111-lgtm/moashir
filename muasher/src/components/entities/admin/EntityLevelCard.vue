<script setup>
import { computed, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useEntityLevelHistory } from '@/composables/useEntityLevelHistory'
import Card from '@/components/ui/Card.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import LevelRibbon from '@/components/level/LevelRibbon.vue'
import LevelHistoryList from '@/components/level/LevelHistoryList.vue'
import { reassessmentOf, tierOfLevel } from '@/utils/levelTier'

const props = defineProps({
  entityId: { type: [Number, String], required: true },
  openAccredited: { type: Boolean, default: false },
})

const { t } = useI18n()
const { status, current, tier, history, load } = useEntityLevelHistory(toRef(props, 'entityId'))
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
    <template v-else>
      <LevelRibbon :tier="ribbonTier" :certificate="current" :reassessment="reassessment" />
      <Card class="entity-level__history" :title="t('level.history.title')">
        <p class="entity-level__note">{{ t('level.history.note') }}</p>
        <LevelHistoryList v-if="history.length" :items="history" />
        <p v-else class="entity-level__empty">{{ t('level.history.empty') }}</p>
      </Card>
    </template>
  </div>
</template>
