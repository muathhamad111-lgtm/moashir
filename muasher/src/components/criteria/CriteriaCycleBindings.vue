<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import CycleStatusBadge from '@/components/award/CycleStatusBadge.vue'
import { localizedText } from '@/utils/localizedText'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  bindings: { type: Object, required: true },
})

const { t, locale } = useI18n()
const baseId = useId()
const running = computed(() => props.bindings.running)
</script>

<template>
  <section class="cycle-bindings" role="note" :aria-labelledby="`${baseId}-title`">
    <h3 :id="`${baseId}-title`" class="cycle-bindings__title">
      <AppIcon name="calendar" />
      {{ t('criteria.cycles.title') }}
    </h3>
    <p v-if="running" class="cycle-bindings__running">
      {{
        t('criteria.cycles.running', {
          name: localizedText(running.name, locale),
          count: running.openRequests,
        })
      }}
      <CycleStatusBadge :status="running.status" />
    </p>
    <p v-else class="cycle-bindings__running">{{ t('criteria.cycles.noRunning') }}</p>
    <p class="cycle-bindings__hint">{{ t('criteria.cycles.newCyclesOnly') }}</p>
    <template v-if="bindings.drafts.length">
      <p class="cycle-bindings__drafts-title">
        {{
          t(
            'criteria.cycles.draftsTitle',
            { count: bindings.drafts.length },
            bindings.drafts.length,
          )
        }}
      </p>
      <ul class="cycle-bindings__drafts">
        <li v-for="draft in bindings.drafts" :key="draft.id" class="cycle-bindings__draft">
          {{ localizedText(draft.name, locale) }}
        </li>
      </ul>
      <p class="cycle-bindings__hint" data-testid="cycle-bindings-drafts-follow">
        {{ t('criteria.cycles.draftsFollow') }}
      </p>
    </template>
  </section>
</template>
