<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import CriteriaTreeList from './CriteriaTreeList.vue'
import { useCriteria } from '@/composables/useCriteria'
import { ancestorsOf, attentionCount, visibleKeys } from '@/utils/criteriaTree'

const props = defineProps({
  index: { type: Map, required: true },
  selectedKey: { type: String, default: null },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['select', 'add', 'reorder'])
const { t } = useI18n()
const { localized } = useCriteria()

const term = ref('')
const attentionOnly = ref(false)
const expanded = ref(new Set())

const mainKeys = computed(() =>
  [...props.index.values()].filter((item) => !item.parentKey).map((item) => item.key),
)
const problems = computed(() => attentionCount(props.index))
const visible = computed(() =>
  visibleKeys(props.index, { term: term.value, attentionOnly: attentionOnly.value }, localized),
)
const nothingFound = computed(() => visible.value && visible.value.size === 0)

watch(
  () => [props.selectedKey, props.index],
  () => {
    if (!props.selectedKey) return
    const opened = [...ancestorsOf(props.index, props.selectedKey), props.selectedKey]
    expanded.value = new Set([...expanded.value, ...opened])
  },
  { immediate: true },
)

function toggle(key) {
  const next = new Set(expanded.value)
  if (next.has(key)) next.delete(key)
  else next.add(key)
  expanded.value = next
}
</script>

<template>
  <nav class="criteria-tree" :aria-label="t('criteria.workspace.tree.title')">
    <header class="criteria-tree__head">
      <h2 class="criteria-tree__heading">{{ t('criteria.workspace.tree.title') }}</h2>
      <BaseButton
        v-if="canEdit"
        variant="secondary"
        size="sm"
        icon="plus"
        @click="emit('add', null)"
        >{{ t('criteria.workspace.tree.addMain') }}</BaseButton
      >
    </header>
    <div class="criteria-tree__filters">
      <BaseInput
        v-model="term"
        name="criteriaSearch"
        type="search"
        :label="t('criteria.workspace.tree.search')"
        :placeholder="t('criteria.workspace.tree.searchPlaceholder')"
      />
      <BaseCheckbox
        v-if="problems"
        v-model="attentionOnly"
        name="criteriaAttentionOnly"
        :label="t('criteria.workspace.tree.attentionOnly', { count: problems }, problems)"
      />
    </div>
    <div class="criteria-tree__scroll">
      <p v-if="!mainKeys.length" class="criteria-tree__empty">
        {{ t('criteria.workspace.tree.empty') }}
      </p>
      <p v-else-if="nothingFound" class="criteria-tree__empty" role="status">
        {{ t('criteria.workspace.tree.noMatch') }}
      </p>
      <CriteriaTreeList
        v-else
        :keys="mainKeys"
        :index="index"
        :visible="visible"
        :expanded="expanded"
        :selected-key="selectedKey"
        :can-edit="canEdit"
        @select="emit('select', $event)"
        @toggle="toggle"
        @add="emit('add', $event)"
        @reorder="emit('reorder', $event)"
      />
    </div>
  </nav>
</template>
