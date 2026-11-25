<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import PageActions from '@/components/ui/PageActions.vue'
import { useEntityActions } from '@/composables/useEntityActions'

const props = defineProps({
  entity: { type: Object, required: true },
})

const emit = defineEmits(['action'])

const { t } = useI18n()
const { actionsFor } = useEntityActions()
const actions = computed(() => actionsFor(props.entity))
</script>

<template>
  <PageActions
    v-if="actions.length"
    class="entity-record__actions"
    role="group"
    :label="t('entities.record.actionsLabel')"
    :aria-label="t('entities.record.actionsLabel')"
  >
    <BaseButton
      v-for="action in actions"
      :key="action.key"
      size="sm"
      :variant="action.variant === 'danger' ? 'danger' : 'secondary'"
      :icon="action.icon"
      @click="emit('action', action.key)"
    >
      {{ action.label }}
    </BaseButton>
  </PageActions>
</template>
