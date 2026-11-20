<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppMenu from '@/components/ui/AppMenu.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Tabs from '@/components/ui/Tabs.vue'
import CriteriaNodeForm from './CriteriaNodeForm.vue'
import CriteriaNodeSummary from './CriteriaNodeSummary.vue'
import CriteriaNodeWeights from './CriteriaNodeWeights.vue'
import CriteriaNodeEvaluation from './CriteriaNodeEvaluation.vue'
import CriteriaChildList from './CriteriaChildList.vue'
import { useCriteria } from '@/composables/useCriteria'
import { useUnsavedGuard } from '@/composables/useUnsavedGuard'
import { nodeLabel, scoresPoints } from '@/utils/criteriaTree'
import { CRITERIA_NODE_TYPES as NODE } from '@/constants/criteria'

// The editor of one criterion, opened on demand from the board: tabs, one save for the form tabs.
const props = defineProps({
  item: { type: Object, required: true },
  index: { type: Map, required: true },
  path: { type: Array, required: true },
  modelWeight: { type: String, required: true },
  initialTab: { type: String, default: 'basics' },
  canEdit: { type: Boolean, default: false },
  canCorrect: { type: Boolean, default: false },
  saveNode: { type: Function, required: true },
  reload: { type: Function, required: true },
})

const emit = defineEmits(['close', 'open', 'add', 'toggle', 'remove', 'correct'])
const { t } = useI18n()
const { localized } = useCriteria()

const TABS = {
  [NODE.MAIN]: ['basics', 'weight', 'children', 'evaluation', 'attachments'],
  pointsSub: ['basics', 'weight', 'evaluation', 'attachments', 'children', 'advanced'],
  directSub: ['basics', 'weight', 'evaluation', 'attachments', 'advanced'],
  [NODE.POINT]: ['basics', 'weight', 'evaluation', 'attachments'],
}
const FORM_TABS = {
  [NODE.MAIN]: ['basics', 'attachments'],
  [NODE.SUB]: ['basics', 'attachments', 'advanced'],
  [NODE.POINT]: ['basics', 'evaluation', 'attachments'],
}

const node = computed(() => props.item.node)
const title = computed(() => localized(nodeLabel(props.item)))
const crumbs = computed(() => props.path.map((key) => props.index.get(key)))
const tabValues = computed(() => {
  if (props.item.type !== NODE.SUB) return TABS[props.item.type]
  return scoresPoints(node.value) ? TABS.pointsSub : TABS.directSub
})
const tab = ref(tabValues.value.includes(props.initialTab) ? props.initialTab : 'basics')
const errorSections = ref([])
const dirty = ref(false)
// The W1 lesson: never drop unsaved edits silently. Closing (button, Esc, backdrop), opening another
// node, a route change or a page reload asks first.
const { allowLeaving } = useUnsavedGuard(dirty, {
  onDiscard: () => (dirty.value = false),
  title: () => t('criteria.workspace.sheet.discardTitle'),
  message: () => t('criteria.workspace.sheet.discardMessage'),
  confirmLabel: () => t('criteria.workspace.sheet.discardConfirm'),
  leavesOnUpdate: (to) => to.query.node !== props.item.key,
})

async function close() {
  if (await allowLeaving()) emit('close')
}

async function open(key, tabName) {
  if (await allowLeaving()) emit('open', key, tabName)
}
const isFormTab = computed(() => FORM_TABS[props.item.type].includes(tab.value))

function labelOf(value) {
  if (value !== 'children') return t(`criteria.workspace.sheet.tabs.${value}`)
  return props.item.type === NODE.MAIN
    ? t('criteria.workspace.sheet.tabs.subs')
    : t('criteria.workspace.sheet.tabs.points')
}

const tabs = computed(() =>
  tabValues.value.map((value) => ({
    value,
    label: labelOf(value),
    ...(errorSections.value.includes(value)
      ? { badge: '!', badgeLabel: t('criteria.workspace.sheet.tabHasErrors') }
      : {}),
  })),
)

const menuActions = computed(() => [
  {
    key: 'toggle',
    label: node.value.active ? t('criteria.actions.deactivate') : t('criteria.actions.activate'),
    icon: 'eye-slash',
  },
  { key: 'remove', label: t('actions.delete'), icon: 'trash', variant: 'danger' },
])
</script>

<template>
  <BaseModal
    :model-value="true"
    layout="drawer"
    size="lg"
    :title="title"
    :subtitle="`${t(`criteria.workspace.details.kind.${item.type}`)} · ${node.code}`"
    @update:model-value="close"
  >
    <div class="criteria-sheet">
      <div class="criteria-sheet__top">
        <ol class="criteria-details__path" :aria-label="t('criteria.workspace.details.path')">
          <li v-for="crumb in crumbs" :key="crumb.key">
            <button type="button" class="criteria-details__path-link" @click="open(crumb.key)">
              {{ localized(nodeLabel(crumb)) }}
            </button>
            <span aria-hidden="true"> › </span>
          </li>
        </ol>
        <StatusBadge :status="node.active ? 'active' : 'inactive'" />
        <span class="criteria-sheet__spacer"></span>
        <BaseButton
          v-if="canCorrect"
          variant="secondary"
          size="sm"
          icon="pen-line"
          @click="emit('correct')"
        >
          {{ t('criteria.actions.correctText') }}
        </BaseButton>
        <AppMenu
          v-if="canEdit"
          :actions="menuActions"
          :label="t('criteria.workspace.sheet.more', { name: title })"
          @select="emit($event)"
        />
      </div>

      <Tabs v-model="tab" :tabs="tabs">
        <CriteriaNodeForm
          v-if="canEdit"
          v-show="isFormTab"
          :item="item"
          :index="index"
          :section="isFormTab ? tab : FORM_TABS[item.type][0]"
          :save-node="saveNode"
          @section-errors="errorSections = $event"
          @dirty="dirty = $event"
        />
        <CriteriaNodeSummary v-if="!canEdit && isFormTab" :item="item" :section="tab" />
        <CriteriaNodeWeights
          v-if="tab === 'weight'"
          :item="item"
          :index="index"
          :model-weight="modelWeight"
          :can-edit="canEdit"
          :reload="reload"
          @open="open"
        />
        <CriteriaNodeEvaluation
          v-if="tab === 'evaluation' && !isFormTab"
          :item="item"
          :can-edit="canEdit"
          :reload="reload"
        />
        <CriteriaChildList
          v-if="tab === 'children'"
          :item="item"
          :index="index"
          :can-edit="canEdit"
          @open="open"
          @add="emit('add', $event)"
        />
      </Tabs>
    </div>
  </BaseModal>
</template>
