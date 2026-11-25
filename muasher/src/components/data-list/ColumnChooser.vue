<script setup>
import { useI18n } from 'vue-i18n'
import AppPopover from '@/components/ui/AppPopover.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'

// Lets a viewer show or hide a list's optional columns. The columns that say what a row is and what
// can be done with it are listed as always shown.
defineProps({
  columns: { type: Array, required: true },
  visible: { type: Array, required: true },
  locked: { type: Array, default: () => [] },
})

const emit = defineEmits(['toggle', 'reset'])
const { t } = useI18n()
</script>

<template>
  <AppPopover
    class="column-chooser"
    trigger-class="btn btn--secondary btn--sm"
    :label="t('components.columns.title')"
    :trigger-label="t('components.columns.open')"
  >
    <template #trigger>
      <AppIcon name="columns" />
      <span>{{ t('components.columns.open') }}</span>
    </template>
    <template #default="{ headingId }">
      <div class="column-chooser__panel">
        <h2 :id="headingId" class="column-chooser__title" tabindex="-1">
          {{ t('components.columns.title') }}
        </h2>
        <p v-if="locked.length" class="column-chooser__locked">
          {{
            t('components.columns.locked', {
              columns: locked.map((column) => column.label).join(t('common.listSeparator')),
            })
          }}
        </p>
        <BaseCheckbox
          v-for="column in columns"
          :key="column.key"
          :name="`column-${column.key}`"
          :label="column.label"
          :model-value="visible.includes(column.key)"
          @update:model-value="emit('toggle', column.key, $event)"
        />
        <button type="button" class="btn btn--ghost btn--sm" @click="emit('reset')">
          {{ t('components.columns.reset') }}
        </button>
      </div>
    </template>
  </AppPopover>
</template>
