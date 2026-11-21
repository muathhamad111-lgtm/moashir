<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppMenu from '@/components/ui/AppMenu.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Tooltip from '@/components/ui/Tooltip.vue'

// The listed actions sit in the row as icon buttons; every other action stays in the "more" menu.
const props = defineProps({
  actions: { type: Array, required: true },
  name: { type: String, required: true },
  inlineKeys: { type: Array, default: () => ['edit'] },
})

const emit = defineEmits(['select'])

const { t } = useI18n()

const inlineActions = computed(() =>
  props.actions.filter((action) => props.inlineKeys.includes(action.key)),
)
const menuActions = computed(() =>
  props.actions.filter((action) => !props.inlineKeys.includes(action.key)),
)
</script>

<template>
  <div class="row-actions">
    <Tooltip v-for="action in inlineActions" :key="action.key" :text="action.label">
      <template #default="{ describedBy }">
        <BaseButton
          variant="secondary"
          size="sm"
          icon-only
          :icon="action.icon"
          :class="action.variant && `row-actions__button--${action.variant}`"
          :aria-label="`${action.label} — ${name}`"
          :aria-describedby="describedBy"
          :data-action="action.key"
          @click="emit('select', action.key)"
        />
      </template>
    </Tooltip>
    <AppMenu
      v-if="menuActions.length"
      class="row-actions__more"
      :actions="menuActions"
      :label="t('components.actions.menuFor', { name })"
      @select="emit('select', $event)"
    />
  </div>
</template>
