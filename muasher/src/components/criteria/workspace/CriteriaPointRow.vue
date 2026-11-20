<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReorderButtons from '@/components/criteria/ReorderButtons.vue'
import CriteriaShare from './CriteriaShare.vue'
import { useCriteria } from '@/composables/useCriteria'
import { useScoringTypeOptions } from '@/composables/useScoringTypeOptions'

const props = defineProps({
  item: { type: Object, required: true },
  sub: { type: Object, required: true },
  position: { type: Object, default: null },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['open', 'move'])
const { t } = useI18n()
const { localized } = useCriteria()
const { labelOf } = useScoringTypeOptions()

const point = computed(() => props.item.node)
const text = computed(() => localized(point.value.description))
const attachments = computed(() => point.value.attachments ?? [])
</script>

<template>
  <li class="criteria-point" :class="{ 'is-inactive': !point.active }">
    <div class="criteria-point__title">
      <bdi class="criteria-code">{{ point.code }}</bdi>
      <div class="criteria-point__text">
        <p class="criteria-point__name">{{ text }}</p>
        <p class="criteria-card__meta">
          <Badge variant="muted">{{ labelOf(point.scoringType) }}</Badge>
          <span v-for="attachment in attachments" :key="attachment.key" class="criteria-card__fact">
            <AppIcon name="attachment" />{{ localized(attachment.name) }}
          </span>
          <Badge v-if="!point.active" variant="muted">{{ t('status.inactive') }}</Badge>
        </p>
      </div>
    </div>
    <div class="criteria-point__side">
      <CriteriaShare :weight="point.weight" :parent-weight="sub.weight" compact />
      <ReorderButtons
        v-if="position"
        :index="position.index"
        :count="position.count"
        :label="text"
        @move="emit('move', $event)"
      />
      <BaseButton
        variant="ghost"
        size="sm"
        :icon="canEdit ? 'pen-line' : 'eye'"
        :aria-label="`${canEdit ? t('actions.edit') : t('criteria.workspace.board.details')} — ${text}`"
        @click="emit('open')"
        >{{ canEdit ? t('actions.edit') : t('criteria.workspace.board.details') }}</BaseButton
      >
    </div>
  </li>
</template>
