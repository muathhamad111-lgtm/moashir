<script setup>
import { computed, ref, toRef, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { TIER_ORDER } from '@/constants/levels'
import { CERTIFICATE_VALIDITY } from '@/constants/certificates'
import { tierPalette } from '@/utils/levelTier'
import { useLevelHeroEntrance } from '@/composables/useLevelHeroEntrance'
import BaseButton from '@/components/ui/BaseButton.vue'
import LevelHeroIdentity from './LevelHeroIdentity.vue'
import LevelHeroProgress from './LevelHeroProgress.vue'
import LevelHeroSkeleton from './LevelHeroSkeleton.vue'

const props = defineProps({
  tier: { type: String, required: true, validator: (value) => TIER_ORDER.includes(value) },
  certificate: { type: Object, default: null },
  ranges: { type: Array, default: () => [] },
  reassessment: { type: Boolean, default: false },
  action: { type: Object, required: true },
  nextLevelTo: { type: Object, default: null },
  playKey: { type: String, required: true },
  loading: { type: Boolean, default: false },
  failed: { type: Boolean, default: false },
})

const emit = defineEmits(['retry'])
const { t } = useI18n()
const root = ref(null)
const nameId = useId()
const settling = ref(false)

const percent = computed(() => props.certificate?.percent ?? null)
const validity = computed(() => props.certificate?.validity ?? null)
const isExpired = computed(() => validity.value === CERTIFICATE_VALIDITY.EXPIRED)
const ready = computed(() => !props.loading && !props.failed)
const { phase, displayScore } = useLevelHeroEntrance(root, {
  ready,
  playKey: toRef(props, 'playKey'),
  score: percent,
})
const classes = computed(() => [
  `level-tone--${tierPalette(props.tier)}`,
  `level-hero--${phase.value}`,
  isExpired.value && 'level-hero--expired',
  settling.value && 'level-hero--settling',
])

watch(
  () => props.loading,
  (isLoading, wasLoading) => (settling.value = Boolean(wasLoading) && !isLoading),
)
</script>

<template>
  <section
    ref="root"
    class="level-hero"
    :class="classes"
    :data-tier="tier"
    :data-validity="validity"
    :data-phase="phase"
    :aria-labelledby="ready ? nameId : undefined"
    :aria-busy="loading || undefined"
  >
    <LevelHeroSkeleton v-if="loading" />
    <div v-else-if="failed" class="level-hero__error" role="alert">
      <p class="level-hero__error-text">{{ t('level.hero.loadError') }}</p>
      <BaseButton size="sm" icon="refresh" @click="emit('retry')">{{
        t('common.retry')
      }}</BaseButton>
    </div>
    <template v-else>
      <LevelHeroIdentity
        :tier="tier"
        :name-id="nameId"
        :certificate="certificate"
        :reassessment="reassessment"
        :action="action"
      />
      <LevelHeroProgress
        :tier="tier"
        :percent="percent"
        :display-score="displayScore"
        :ranges="ranges"
        :next-level-to="isExpired ? null : nextLevelTo"
      />
    </template>
  </section>
</template>
