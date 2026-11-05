<script setup>
import AreaTabs from './AreaTabs.vue'
import PortalBreadcrumb from './PortalBreadcrumb.vue'

defineProps({
  title: { type: String, required: true },
  eyebrow: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  breadcrumbs: { type: Array, default: () => [] },
  tabs: { type: Array, default: () => [] },
  tab: { type: String, default: '' },
  tabsLabel: { type: String, default: '' },
  panelId: { type: String, default: '' },
})

const emit = defineEmits(['update:tab'])
</script>

<template>
  <header class="portal-area__header">
    <div class="portal-area__hero">
      <div class="portal-area__intro">
        <PortalBreadcrumb v-if="breadcrumbs.length" :items="breadcrumbs" />
        <p v-if="eyebrow" class="portal-area__eyebrow">{{ eyebrow }}</p>
        <h1 class="portal-area__title">{{ title }}</h1>
        <p v-if="subtitle" class="portal-area__subtitle">{{ subtitle }}</p>
      </div>
      <div v-if="$slots.actions" class="portal-area__actions">
        <slot name="actions" />
      </div>
    </div>
    <AreaTabs
      v-if="tabs.length"
      class="portal-area__tabs"
      :tabs="tabs"
      :model-value="tab"
      :label="tabsLabel"
      :panel-id="panelId"
      @update:model-value="emit('update:tab', $event)"
    />
  </header>
</template>
