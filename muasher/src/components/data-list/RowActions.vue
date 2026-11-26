<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppMenu from '@/components/ui/AppMenu.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Tooltip from '@/components/ui/Tooltip.vue'

// At most two of the listed actions sit in the row as icon buttons; every other action stays in the
// "more" menu, so a row never grows a strip of icons.
const MAX_INLINE = 2

const props = defineProps({
  actions: { type: Array, required: true },
  name: { type: String, required: true },
  inlineKeys: { type: Array, default: () => ['edit'] },
})

const emit = defineEmits(['select'])

const { t } = useI18n()

// In the order of `inlineKeys`, so the list decides which two come first.
const inlineActions = computed(() =>
  props.inlineKeys
    .map((key) => props.actions.find((action) => action.key === key))
    .filter(Boolean)
    .slice(0, MAX_INLINE),
)
const menuActions = computed(() =>
  props.actions.filter((action) => !inlineActions.value.includes(action)),
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
