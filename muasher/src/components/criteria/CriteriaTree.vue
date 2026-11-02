<script setup>
import { ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  nodes: { type: Array, default: () => [] },
  activeId: { type: [Number, String, null], default: null },
})

const emit = defineEmits(['select'])
const { t } = useI18n()
const baseId = useId()
const openIds = ref(props.nodes.length ? [props.nodes[0].id] : [])

function isOpen(node) {
  return openIds.value.includes(node.id)
}

function toggle(node) {
  openIds.value = isOpen(node)
    ? openIds.value.filter((id) => id !== node.id)
    : [...openIds.value, node.id]
}

function branchId(node) {
  return `${baseId}-branch-${node.id}`
}

function panelId(node) {
  return `${baseId}-panel-${node.id}`
}

function hasChildren(node) {
  return Array.isArray(node.children) && node.children.length > 0
}
</script>

<template>
  <nav class="criteria-tree" :aria-label="t('criteria.tree.label')">
    <p v-if="!nodes.length" class="criteria-tree__empty">{{ t('criteria.tree.empty') }}</p>
    <ul v-else class="criteria-tree__list">
      <li
        v-for="node in nodes"
        :key="node.id"
        class="criteria-tree__branch"
        :class="{ 'is-open': isOpen(node), 'is-inactive': node.active === false }"
      >
        <div class="criteria-tree__row">
          <button
            :id="branchId(node)"
            type="button"
            class="criteria-tree__toggle"
            :aria-expanded="isOpen(node)"
            :aria-controls="panelId(node)"
            @click="toggle(node)"
          >
            <i class="fa-solid fa-chevron-down criteria-tree__chevron" aria-hidden="true"></i>
            <span class="criteria-tree__label">{{ node.label }}</span>
          </button>
          <button
            type="button"
            class="criteria-tree__open"
            :class="{ 'is-active': node.id === activeId }"
            @click="emit('select', { level: 'main', node })"
          >
            {{ node.meta || t('criteria.tree.open') }}
          </button>
        </div>
        <div
          v-if="isOpen(node)"
          :id="panelId(node)"
          class="criteria-tree__panel"
          role="region"
          :aria-labelledby="branchId(node)"
        >
          <ul v-if="hasChildren(node)" class="criteria-tree__children">
            <li
              v-for="child in node.children"
              :key="child.id"
              class="criteria-tree__child"
              :class="{ 'is-inactive': child.active === false }"
            >
              <button
                type="button"
                class="criteria-tree__child-btn"
                :class="{ 'is-active': child.id === activeId }"
                @click="emit('select', { level: 'sub', node: child, parent: node })"
              >
                <span class="criteria-tree__label">{{ child.label }}</span>
                <span v-if="child.meta" class="criteria-tree__meta">{{ child.meta }}</span>
              </button>
              <ul v-if="hasChildren(child)" class="criteria-tree__points">
                <li v-for="point in child.children" :key="point.id" class="criteria-tree__point">
                  <span class="criteria-tree__point-marker" aria-hidden="true"></span>
                  <span class="criteria-tree__label">{{ point.label }}</span>
                  <span v-if="point.meta" class="criteria-tree__meta">{{ point.meta }}</span>
                </li>
              </ul>
            </li>
          </ul>
          <p v-else class="criteria-tree__no-children">{{ t('criteria.tree.noChildren') }}</p>
        </div>
      </li>
    </ul>
  </nav>
</template>
