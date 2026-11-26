<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCriteria } from '@/composables/useCriteria'
import { formatShare, shareOf } from '@/utils/weight'

// A weight shown as its share of the parent, with the stored points underneath.
const props = defineProps({
  weight: { type: String, required: true },
  parentWeight: { type: String, required: true },
  caption: { type: String, default: '' },
  compact: { type: Boolean, default: false },
})

const { t, locale } = useI18n()
const { weight: points } = useCriteria()

const share = computed(() => formatShare(shareOf(props.weight, props.parentWeight), locale.value))
</script>

<template>
  <div class="criteria-share" :class="{ 'criteria-share--compact': compact }">
    <span v-if="caption" class="criteria-share__caption">{{ caption }}</span>
    <bdi class="criteria-share__value" dir="ltr">{{ share }}</bdi>
    <span v-if="!compact" class="criteria-share__points">{{
      t('criteria.workspace.board.pointsValue', { weight: points(weight) })
    }}</span>
  </div>
</template>
