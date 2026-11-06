<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import { hasIcon } from '@/utils/icons/iconRegistry'

const props = defineProps({
  icon: { type: String, required: true },
  label: { type: String, required: true },
  value: { type: String, default: '' },
  to: { type: [String, Object], default: null },
  tone: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'danger'].includes(value),
  },
})

const emit = defineEmits(['select'])
const drawable = computed(() => hasIcon(props.icon))
</script>

<template>
  <component
    :is="to ? RouterLink : 'button'"
    :to="to || undefined"
    :type="to ? undefined : 'button'"
    class="portal-menu-entry"
    :class="`portal-menu-entry--${tone}`"
    @click="emit('select')"
  >
    <AppIcon v-if="drawable" :name="icon" class="portal-menu-entry__icon" />
    <span v-else class="portal-menu-entry__icon" aria-hidden="true"></span>
    <span class="portal-menu-entry__label">{{ label }}</span>
    <bdi v-if="value" class="portal-menu-entry__value">{{ value }}</bdi>
  </component>
</template>
