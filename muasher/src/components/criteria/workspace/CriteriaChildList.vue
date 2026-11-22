<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import CriteriaShare from './CriteriaShare.vue'
import { useCriteria } from '@/composables/useCriteria'
import { nodeLabel } from '@/utils/criteriaTree'
import { CRITERIA_NODE_TYPES as NODE } from '@/constants/criteria'

// The sub-criteria or indicators tab of the editor sheet.
const props = defineProps({
  item: { type: Object, required: true },
  index: { type: Map, required: true },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['open', 'add'])
const { t } = useI18n()
const { localized } = useCriteria()

const children = computed(() => props.item.children.map((key) => props.index.get(key)))
const emptyLabel = computed(() =>
  props.item.type === NODE.MAIN
    ? t('criteria.workspace.board.noSubs')
    : t('criteria.workspace.board.noPoints'),
)
const addLabel = computed(() =>
  props.item.type === NODE.MAIN
    ? t('criteria.main.addSub')
    : t('criteria.workspace.details.addPoint'),
)
</script>

<template>
  <div class="criteria-sheet__stack">
    <ul v-if="children.length" class="criteria-card__point-list">
      <li v-for="child in children" :key="child.key" class="criteria-point">
        <div class="criteria-point__title">
          <bdi class="criteria-code">{{ child.node.code }}</bdi>
          <p class="criteria-point__name">{{ localized(nodeLabel(child)) }}</p>
        </div>
        <div class="criteria-point__side">
          <CriteriaShare
            v-if="child.node.weight !== null"
            :weight="child.node.weight"
            :parent-weight="item.node.weight"
            compact
          />
          <BaseButton
            variant="ghost"
            size="sm"
            icon="chevron-right"
            @click="emit('open', child.key)"
            >{{ t('criteria.workspace.sheet.open') }}</BaseButton
          >
        </div>
      </li>
    </ul>
    <p v-else class="criteria-card__empty">{{ emptyLabel }}</p>
    <button
      v-if="canEdit"
      type="button"
      class="criteria-add-row criteria-add-row--sm"
      @click="emit('add', item.key)"
    >
      <AppIcon name="plus" />{{ addLabel }}
    </button>
  </div>
</template>
