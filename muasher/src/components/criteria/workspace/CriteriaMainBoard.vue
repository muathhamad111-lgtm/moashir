<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ReorderButtons from '@/components/criteria/ReorderButtons.vue'
import CriteriaShare from './CriteriaShare.vue'
import CriteriaWeightSplit from './CriteriaWeightSplit.vue'
import CriteriaSubCard from './CriteriaSubCard.vue'
import CriteriaAttachmentChips from './CriteriaAttachmentChips.vue'
import { useCriteria } from '@/composables/useCriteria'
import { useCriteriaAction } from '@/composables/useCriteriaAction'
import { useScoringTypeOptions } from '@/composables/useScoringTypeOptions'
import { criteriaService } from '@/services/criteriaService'
import { compareWeights } from '@/utils/weight'
import { moveIndex } from '@/utils/reorder'

// The selected main criterion at full width: its split, its sub-criteria and their indicators.
const props = defineProps({
  item: { type: Object, required: true },
  index: { type: Map, required: true },
  modelWeight: { type: String, required: true },
  position: { type: Object, default: null },
  canEdit: { type: Boolean, default: false },
  reload: { type: Function, required: true },
})

const emit = defineEmits(['open', 'add', 'move', 'reorder'])
const { t } = useI18n()
const { localized, weight } = useCriteria()
const { labelOf } = useScoringTypeOptions()
const { run } = useCriteriaAction(() => props.reload())

const main = computed(() => props.item.node)
const name = computed(() => localized(main.value.name))
const subs = computed(() => props.item.children.map((key) => props.index.get(key)))
const splitItems = computed(() =>
  subs.value.map(({ key, node }) => ({
    key,
    code: node.code,
    weight: node.weight,
    active: node.active,
  })),
)
const isFull = computed(
  () => !main.value.distributeEqually && compareWeights(main.value.remainingWeight, '0') <= 0,
)
const attachments = computed(() => main.value.attachments ?? [])

function subPosition(at) {
  return props.canEdit && subs.value.length > 1 ? { index: at, count: subs.value.length } : null
}

function moveSub(from, step) {
  const ordered = moveIndex(props.item.children, from, from + step)
  emit('reorder', {
    type: subs.value[0].type,
    parentKey: props.item.key,
    ids: ordered.map((key) => props.index.get(key).node.id),
  })
}

const shareEqually = () =>
  run(() => criteriaService.updateMain(main.value.id, { distributeEqually: true }), {
    success: 'criteria.weights.modeSaved',
  })

async function addSub() {
  if (isFull.value && !(await shareEqually())) return
  emit('add', props.item.key)
}
</script>

<template>
  <div class="criteria-board">
    <section class="criteria-board__head" :aria-labelledby="`criteria-main-${main.id}`">
      <div class="criteria-board__summary">
        <p class="criteria-card__meta">
          <bdi class="criteria-code">{{ main.code }}</bdi>
          <span>{{ t(`criteria.groups.${main.group}`) }}</span>
          <span>{{
            t('criteria.workspace.board.defaultType', { type: labelOf(main.defaultScoringType) })
          }}</span>
        </p>
        <div class="criteria-board__title-row">
          <h2 :id="`criteria-main-${main.id}`" class="criteria-board__title">{{ name }}</h2>
          <StatusBadge :status="main.active ? 'active' : 'inactive'" />
        </div>
        <p v-if="localized(main.description)" class="criteria-card__description">
          {{ localized(main.description) }}
        </p>
        <CriteriaWeightSplit
          v-if="splitItems.length"
          :parent-weight="main.weight"
          :items="splitItems"
        />
      </div>
      <CriteriaShare
        :weight="main.weight"
        :parent-weight="modelWeight"
        :caption="t('criteria.workspace.board.shareOfModel')"
      />
      <div class="criteria-board__actions">
        <BaseButton
          variant="secondary"
          size="sm"
          :icon="canEdit ? 'pen-line' : 'eye'"
          @click="emit('open', item.key)"
          >{{ canEdit ? t('actions.edit') : t('criteria.workspace.board.details') }}</BaseButton
        >
        <BaseButton v-if="canEdit" variant="primary" size="sm" icon="plus" @click="addSub">{{
          isFull ? t('criteria.workspace.details.shareAndAddSub') : t('criteria.main.addSub')
        }}</BaseButton>
        <ReorderButtons
          v-if="position"
          :index="position.index"
          :count="position.count"
          :label="name"
          @move="emit('move', $event)"
        />
      </div>
    </section>

    <div v-if="item.attention" class="criteria-board__alert" role="status">
      <AppIcon name="warning-triangle" />
      <span>{{
        t('criteria.workspace.board.unbalanced', {
          allocated: weight(main.allocatedWeight),
          weight: weight(main.weight),
        })
      }}</span>
      <template v-if="canEdit">
        <BaseButton variant="secondary" size="sm" @click="shareEqually">{{
          t('criteria.workspace.board.splitEqually')
        }}</BaseButton>
        <BaseButton variant="ghost" size="sm" @click="emit('open', item.key, 'weight')">{{
          t('criteria.workspace.board.splitManually')
        }}</BaseButton>
      </template>
    </div>

    <section v-if="attachments.length" class="criteria-card criteria-card--plain">
      <div class="criteria-card__body">
        <CriteriaAttachmentChips
          :attachments="attachments"
          :label="t('criteria.workspace.board.mainAttachments')"
        />
      </div>
    </section>

    <CriteriaSubCard
      v-for="(sub, at) in subs"
      :key="sub.key"
      :item="sub"
      :index="index"
      :main="main"
      :position="subPosition(at)"
      :can-edit="canEdit"
      @open="emit('open', $event)"
      @add="emit('add', $event)"
      @move="moveSub(at, $event)"
      @reorder="emit('reorder', $event)"
    />
    <p v-if="!subs.length" class="criteria-card__empty">
      <Badge variant="muted">{{ t('criteria.workspace.board.noSubs') }}</Badge>
    </p>
    <button v-if="canEdit" type="button" class="criteria-add-row" @click="addSub">
      <AppIcon name="plus" />{{
        isFull ? t('criteria.workspace.details.shareAndAddSub') : t('criteria.main.addSub')
      }}
    </button>
  </div>
</template>
