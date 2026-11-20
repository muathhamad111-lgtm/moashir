<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReorderButtons from '@/components/criteria/ReorderButtons.vue'
import CriteriaShare from './CriteriaShare.vue'
import CriteriaPointRow from './CriteriaPointRow.vue'
import CriteriaAttachmentChips from './CriteriaAttachmentChips.vue'
import { useCriteria } from '@/composables/useCriteria'
import { useScoringTypeOptions } from '@/composables/useScoringTypeOptions'
import { scoresPoints } from '@/utils/criteriaTree'
import { moveIndex } from '@/utils/reorder'

const props = defineProps({
  item: { type: Object, required: true },
  index: { type: Map, required: true },
  main: { type: Object, required: true },
  position: { type: Object, default: null },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['open', 'add', 'move', 'reorder'])
const { t } = useI18n()
const { localized } = useCriteria()
const { labelOf } = useScoringTypeOptions()

const sub = computed(() => props.item.node)
const name = computed(() => localized(sub.value.name))
const onPoints = computed(() => scoresPoints(sub.value))
const points = computed(() => props.item.children.map((key) => props.index.get(key)))
const attachments = computed(() => sub.value.attachments ?? [])
const canReorderPoints = computed(() => props.canEdit && points.value.length > 1)
const headingId = computed(() => `criteria-sub-${sub.value.id}`)

function pointPosition(at) {
  return canReorderPoints.value ? { index: at, count: points.value.length } : null
}

function movePoint(from, step) {
  const ordered = moveIndex(props.item.children, from, from + step)
  emit('reorder', {
    type: points.value[0].type,
    parentKey: props.item.key,
    ids: ordered.map((key) => props.index.get(key).node.id),
  })
}
</script>

<template>
  <section
    class="criteria-card"
    :class="{ 'is-attention': item.attention, 'is-inactive': !sub.active }"
    :aria-labelledby="headingId"
  >
    <header class="criteria-card__head">
      <div class="criteria-card__title">
        <bdi class="criteria-code">{{ sub.code }}</bdi>
        <div>
          <h3 :id="headingId" class="criteria-card__name">{{ name }}</h3>
          <p class="criteria-card__meta">
            <Badge variant="muted">{{
              onPoints
                ? t('criteria.evaluationType.guidingPoints')
                : t('criteria.evaluationType.direct')
            }}</Badge>
            <Badge v-if="onPoints" variant="muted">{{
              t('criteria.workspace.board.pointsCount', { count: points.length }, points.length)
            }}</Badge>
            <Badge v-else variant="muted">{{ labelOf(sub.scoringType) }}</Badge>
            <span v-if="attachments.length" class="criteria-card__fact">
              <AppIcon name="attachment" />{{ attachments.length }}
            </span>
            <Badge v-if="item.attention" variant="danger">{{
              t('criteria.workspace.tree.attention')
            }}</Badge>
            <Badge v-if="!sub.active" variant="muted">{{ t('status.inactive') }}</Badge>
          </p>
        </div>
      </div>
      <div class="criteria-card__side">
        <CriteriaShare
          :weight="sub.weight"
          :parent-weight="main.weight"
          :caption="t('criteria.workspace.board.shareOfMain')"
        />
        <ReorderButtons
          v-if="position"
          :index="position.index"
          :count="position.count"
          :label="name"
          @move="emit('move', $event)"
        />
        <BaseButton
          variant="secondary"
          size="sm"
          :icon="canEdit ? 'pen-line' : 'eye'"
          :aria-label="`${canEdit ? t('actions.edit') : t('criteria.workspace.board.details')} — ${name}`"
          @click="emit('open', item.key)"
          >{{ canEdit ? t('actions.edit') : t('criteria.workspace.board.details') }}</BaseButton
        >
      </div>
    </header>

    <div class="criteria-card__body">
      <p v-if="localized(sub.description)" class="criteria-card__description">
        {{ localized(sub.description) }}
      </p>
      <CriteriaAttachmentChips
        v-if="attachments.length"
        :attachments="attachments"
        :label="t('criteria.attachments.title')"
      />
      <div v-if="onPoints" class="criteria-card__points">
        <span class="criteria-card__label">{{ t('criteria.workspace.board.pointsTitle') }}</span>
        <ul v-if="points.length" class="criteria-card__point-list">
          <CriteriaPointRow
            v-for="(point, at) in points"
            :key="point.key"
            :item="point"
            :sub="sub"
            :position="pointPosition(at)"
            :can-edit="canEdit"
            @open="emit('open', point.key)"
            @move="movePoint(at, $event)"
          />
        </ul>
        <p v-else class="criteria-card__empty">{{ t('criteria.workspace.board.noPoints') }}</p>
        <button
          v-if="canEdit"
          type="button"
          class="criteria-add-row criteria-add-row--sm"
          @click="emit('add', item.key)"
        >
          <AppIcon name="plus" />{{ t('criteria.workspace.details.addPoint') }}
        </button>
      </div>
      <p v-else class="criteria-card__note">
        <AppIcon name="info-circle" />
        {{ t('criteria.workspace.board.directNote', { type: labelOf(sub.scoringType) }) }}
      </p>
    </div>
  </section>
</template>
