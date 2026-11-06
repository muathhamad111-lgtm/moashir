<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import Badge from '@/components/ui/Badge.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import SortableList from '@/components/ui/SortableList.vue'
import { sectionLabel } from '@/utils/cmsPayload'

const props = defineProps({
  sections: { type: Array, required: true },
  localized: { type: Function, required: true },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['reorder', 'toggle', 'edit', 'items', 'remove'])
const { t } = useI18n()

function labelOf(section) {
  return sectionLabel(section, props.localized)
}
</script>

<template>
  <SortableList
    :items="sections"
    :label-of="labelOf"
    :list-label="t('cms.sections.listLabel')"
    :disabled="busy"
    @reorder="emit('reorder', $event)"
  >
    <template #item="{ item }">
      <div class="cms-section-row" :class="{ 'is-disabled': !item.enabled }">
        <div class="cms-section-row__info">
          <span class="cms-section-row__name">{{ labelOf(item) }}</span>
          <span class="cms-section-row__meta">
            <Badge variant="muted">{{ t(`cms.sectionTypes.${item.type}`) }}</Badge>
            <span class="cms-section-row__key u-ltr">{{ item.key }}</span>
            <span v-if="item.items.length" class="cms-section-row__count">{{
              t('cms.sections.itemCount', { count: item.items.length })
            }}</span>
          </span>
        </div>
        <ToggleSwitch
          :model-value="item.enabled"
          :name="`section-enabled-${item.id}`"
          :label="item.enabled ? t('cms.enabled') : t('cms.disabled')"
          :disabled="busy"
          @update:model-value="emit('toggle', item, $event)"
        />
        <div class="cms-section-row__actions">
          <BaseButton
            variant="secondary"
            size="sm"
            icon="pen"
            :disabled="busy"
            @click="emit('edit', item)"
          >
            {{ t('actions.edit') }}
          </BaseButton>
          <BaseButton
            variant="ghost"
            size="sm"
            icon="layers"
            :disabled="busy"
            @click="emit('items', item)"
          >
            {{ t('cms.sections.items') }}
          </BaseButton>
          <BaseButton
            variant="ghost"
            size="sm"
            icon-only
            icon="trash"
            :aria-label="t('cms.sections.removeNamed', { name: labelOf(item) })"
            :disabled="busy"
            @click="emit('remove', item)"
          />
        </div>
      </div>
    </template>
  </SortableList>
</template>
