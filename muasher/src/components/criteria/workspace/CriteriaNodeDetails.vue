<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import GuidingPointForm from '@/components/criteria/GuidingPointForm.vue'
import CriteriaMainDetails from './CriteriaMainDetails.vue'
import CriteriaSubDetails from './CriteriaSubDetails.vue'
import { useCriteria } from '@/composables/useCriteria'
import { nodeLabel } from '@/utils/criteriaTree'
import { CRITERIA_NODE_TYPES as NODE } from '@/constants/criteria'

const props = defineProps({
  item: { type: Object, required: true },
  index: { type: Map, required: true },
  path: { type: Array, required: true },
  canEdit: { type: Boolean, default: false },
  canCorrect: { type: Boolean, default: false },
  saveNode: { type: Function, required: true },
  reload: { type: Function, required: true },
})

const emit = defineEmits(['select', 'add', 'toggle', 'remove', 'correct'])
const { t } = useI18n()
const { localized } = useCriteria()

const node = computed(() => props.item.node)
const title = computed(() => localized(nodeLabel(props.item)))
const children = computed(() => props.item.children.map((key) => props.index.get(key)))
const parent = computed(() => props.index.get(props.item.parentKey)?.node ?? null)
const crumbs = computed(() => props.path.map((key) => props.index.get(key)))
const root = ref(null)

onMounted(() => {
  const top = root.value?.getBoundingClientRect().top ?? 0
  if (top < 0 || top > window.innerHeight) root.value.scrollIntoView({ block: 'start' })
})
</script>

<template>
  <section ref="root" class="criteria-details" :aria-label="title">
    <header class="criteria-details__header">
      <div class="criteria-details__heading">
        <ol class="criteria-details__path" :aria-label="t('criteria.workspace.details.path')">
          <li>
            <button type="button" class="criteria-details__path-link" @click="emit('select', null)">
              {{ t('criteria.workspace.details.allCriteria') }}
            </button>
          </li>
          <li v-for="crumb in crumbs" :key="crumb.key">
            /
            <button
              type="button"
              class="criteria-details__path-link"
              @click="emit('select', crumb.key)"
            >
              {{ localized(nodeLabel(crumb)) }}
            </button>
          </li>
        </ol>
        <div class="criteria-details__title-row">
          <h2 class="criteria-details__title">{{ title }}</h2>
          <StatusBadge :status="node.active ? 'active' : 'inactive'" />
        </div>
        <p class="criteria-details__note">
          {{ t(`criteria.workspace.details.kind.${item.type}`) }} · {{ node.code }}
        </p>
      </div>
      <div class="criteria-details__actions">
        <BaseButton v-if="canCorrect" variant="secondary" icon="pen-line" @click="emit('correct')">
          {{ t('criteria.actions.correctText') }}
        </BaseButton>
        <template v-if="canEdit">
          <BaseButton variant="secondary" @click="emit('toggle')">
            {{ node.active ? t('criteria.actions.deactivate') : t('criteria.actions.activate') }}
          </BaseButton>
          <BaseButton variant="danger" icon="trash" @click="emit('remove')">
            {{ t('actions.delete') }}
          </BaseButton>
        </template>
      </div>
    </header>

    <CriteriaMainDetails
      v-if="item.type === NODE.MAIN"
      :item="item"
      :children="children"
      :can-edit="canEdit"
      :save-node="saveNode"
      :reload="reload"
      @add="emit('add', item.key)"
    />
    <CriteriaSubDetails
      v-else-if="item.type === NODE.SUB"
      :item="item"
      :main="parent"
      :children="children"
      :can-edit="canEdit"
      :save-node="saveNode"
      :reload="reload"
      @add="emit('add', item.key)"
    />
    <GuidingPointForm
      v-else
      :model-value="true"
      inline
      :readonly="!canEdit"
      :point="node"
      :sub="parent"
      :save="saveNode"
    />
  </section>
</template>
