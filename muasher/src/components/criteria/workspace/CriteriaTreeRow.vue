<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import ReorderButtons from '@/components/criteria/ReorderButtons.vue'
import { useCriteria } from '@/composables/useCriteria'
import { useScoringTypeOptions } from '@/composables/useScoringTypeOptions'
import { nodeLabel, scoresPoints } from '@/utils/criteriaTree'
import { CRITERIA_NODE_TYPES as NODE } from '@/constants/criteria'

const props = defineProps({
  item: { type: Object, required: true },
  selected: { type: Boolean, default: false },
  expanded: { type: Boolean, default: false },
  canAdd: { type: Boolean, default: false },
  position: { type: Object, default: null },
})

const emit = defineEmits(['select', 'toggle', 'add', 'move'])
const { t } = useI18n()
const { localized, weight } = useCriteria()
const { labelOf, shortOf } = useScoringTypeOptions()

const node = computed(() => props.item.node)
const name = computed(() => localized(nodeLabel(props.item)))
const hasChildren = computed(() => props.item.children.length > 0)
const isDirectSub = computed(() => props.item.type === NODE.SUB && !scoresPoints(node.value))
const acceptsChildren = computed(
  () => props.item.type === NODE.MAIN || (props.item.type === NODE.SUB && !isDirectSub.value),
)
const attachmentCount = computed(() => node.value.attachments?.length ?? 0)
const scoringType = computed(() =>
  props.item.type === NODE.POINT || isDirectSub.value ? node.value.scoringType : null,
)
const addLabel = computed(() =>
  t(`criteria.workspace.tree.add.${props.item.type}`, { name: name.value }),
)
</script>

<template>
  <div class="criteria-tree__row" :class="{ 'is-selected': selected, 'is-inactive': !node.active }">
    <button
      v-if="hasChildren"
      type="button"
      class="criteria-tree__toggle"
      :aria-expanded="expanded ? 'true' : 'false'"
      :aria-label="t('criteria.workspace.tree.toggle', { name })"
      @click="emit('toggle')"
    >
      <AppIcon name="chevron-down" />
    </button>
    <span v-else class="criteria-tree__spacer" aria-hidden="true"></span>

    <button
      type="button"
      class="criteria-tree__select"
      :aria-current="selected ? 'true' : undefined"
      @click="emit('select')"
    >
      <span class="criteria-tree__name" :title="name">{{ name }}</span>
      <span
        v-if="item.attention"
        class="criteria-tree__marker criteria-tree__marker--attention"
        :title="t('criteria.workspace.tree.attention')"
      >
        <AppIcon name="warning-triangle" />
        <span class="criteria-tree__sr">{{ t('criteria.workspace.tree.attention') }}</span>
      </span>
      <span v-if="attachmentCount" class="criteria-tree__marker">
        <AppIcon name="attachment" />
        <span class="criteria-tree__sr">{{
          t('criteria.workspace.tree.attachments', { count: attachmentCount }, attachmentCount)
        }}</span>
        <span aria-hidden="true">{{ attachmentCount }}</span>
      </span>
      <span v-if="isDirectSub" class="criteria-tree__marker">{{
        t('criteria.workspace.tree.direct')
      }}</span>
      <span v-if="scoringType" class="criteria-tree__marker" :title="labelOf(scoringType)">
        <span aria-hidden="true">{{ shortOf(scoringType) }}</span>
        <span class="criteria-tree__sr">{{ labelOf(scoringType) }}</span>
      </span>
      <span v-if="!node.active" class="criteria-tree__sr">{{ t('status.inactive') }}</span>
      <span class="criteria-tree__weight">{{ weight(node.weight) }}</span>
    </button>

    <div v-if="canAdd || position" class="criteria-tree__moves">
      <BaseButton
        v-if="canAdd && acceptsChildren"
        variant="ghost"
        size="sm"
        icon-only
        icon="plus"
        :aria-label="addLabel"
        :title="addLabel"
        @click="emit('add')"
      />
      <ReorderButtons
        v-if="position"
        :index="position.index"
        :count="position.count"
        :label="name"
        @move="emit('move', $event)"
      />
    </div>
  </div>
</template>
