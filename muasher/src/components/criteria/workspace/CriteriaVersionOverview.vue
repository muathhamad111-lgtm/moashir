<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useCriteria } from '@/composables/useCriteria'
import { CRITERIA_NODE_TYPES as NODE } from '@/constants/criteria'

const props = defineProps({
  tree: { type: Object, required: true },
  index: { type: Map, required: true },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['add'])
const { t } = useI18n()
const { weight } = useCriteria()

function countOf(type) {
  return [...props.index.values()].filter((item) => item.type === type).length
}

const facts = computed(() => [
  { key: 'mains', value: countOf(NODE.MAIN) },
  { key: 'subs', value: countOf(NODE.SUB) },
  { key: 'points', value: countOf(NODE.POINT) },
  { key: 'totalWeight', value: weight(props.tree.activeWeightTotal ?? '0') },
])
</script>

<template>
  <Card :title="t('criteria.workspace.overview.title')">
    <dl class="criteria-overview__facts">
      <div v-for="fact in facts" :key="fact.key" class="criteria-overview__fact">
        <dt>{{ t(`criteria.workspace.overview.${fact.key}`) }}</dt>
        <dd>{{ fact.value }}</dd>
      </div>
    </dl>
    <p class="criteria-details__note">{{ t('criteria.workspace.overview.hint') }}</p>
    <template v-if="canEdit && !countOf(NODE.MAIN)" #footer>
      <BaseButton variant="primary" icon="plus" @click="emit('add')">
        {{ t('criteria.workspace.tree.addMain') }}
      </BaseButton>
    </template>
  </Card>
</template>
