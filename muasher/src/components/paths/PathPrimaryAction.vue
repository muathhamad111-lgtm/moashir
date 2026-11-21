<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  action: { type: Object, default: null },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['run'])

const { t } = useI18n()
</script>

<template>
  <button
    v-if="action?.run"
    type="button"
    class="btn btn--on-band path-primary-action"
    data-testid="path-primary-action"
    :disabled="busy"
    :aria-busy="busy || undefined"
    @click="emit('run', action.run)"
  >
    <span>{{ t(action.labelKey) }}</span>
    <AppIcon :name="busy ? 'spinner' : action.icon" />
  </button>
  <router-link
    v-else-if="action"
    class="btn btn--on-band path-primary-action"
    :to="action.to"
    data-testid="path-primary-action"
  >
    <span>{{ t(action.labelKey) }}</span>
    <AppIcon :name="action.icon" />
  </router-link>
</template>
