<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import CycleStatusBadge from '@/components/award/CycleStatusBadge.vue'
import { DRAFT_CYCLE_CHOICES } from '@/constants/committee'
import { localizedText } from '@/utils/localizedText'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  bindings: { type: Object, required: true },
  choice: { type: String, default: DRAFT_CYCLE_CHOICES.KEEP },
  showChoice: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:choice'])
const { t, locale } = useI18n()
const baseId = useId()
const choices = computed(() => Object.values(DRAFT_CYCLE_CHOICES))
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
      <fieldset v-if="showChoice" class="cycle-bindings__choice" :disabled="disabled">
        <legend class="cycle-bindings__legend">{{ t('criteria.cycles.choiceLegend') }}</legend>
        <label
          v-for="value in choices"
          :key="value"
          class="cycle-bindings__option"
          :class="{ 'is-selected': choice === value }"
        >
          <input
            class="cycle-bindings__input"
            type="radio"
            :name="`${baseId}-draft-cycles`"
            :value="value"
            :checked="choice === value"
            @change="emit('update:choice', value)"
          />
          <span class="cycle-bindings__option-text">
            <span class="cycle-bindings__option-title">{{
              t(`criteria.cycles.choices.${value}.title`)
            }}</span>
            <span class="cycle-bindings__option-description">{{
              t(`criteria.cycles.choices.${value}.description`)
            }}</span>
          </span>
        </label>
      </fieldset>
    </template>
  </section>
</template>
