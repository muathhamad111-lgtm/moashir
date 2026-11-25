<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import Tooltip from '@/components/ui/Tooltip.vue'

// The «الأعمدة» button. A dot marks a view that differs from the viewer's saved default.
defineProps({
  unsaved: { type: Boolean, default: false },
})

const emit = defineEmits(['open'])
const { t } = useI18n()
</script>

<template>
  <Tooltip v-if="unsaved" :text="t('components.columns.unsaved')">
    <template #default="{ describedBy }">
      <button
        type="button"
        class="btn btn--secondary btn--sm column-manager__trigger is-unsaved"
        aria-haspopup="dialog"
        :aria-label="`${t('components.columns.open')} (${t('components.columns.unsaved')})`"
        :aria-describedby="describedBy"
        @click="emit('open')"
      >
        <AppIcon name="columns" />
        <span>{{ t('components.columns.open') }}</span>
        <span class="column-manager__dot" aria-hidden="true" />
      </button>
    </template>
  </Tooltip>
  <button
    v-else
    type="button"
    class="btn btn--secondary btn--sm column-manager__trigger"
    aria-haspopup="dialog"
    @click="emit('open')"
  >
    <AppIcon name="columns" />
    <span>{{ t('components.columns.open') }}</span>
  </button>
</template>
