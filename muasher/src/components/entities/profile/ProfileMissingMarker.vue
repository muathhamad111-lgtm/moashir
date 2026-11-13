<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  label: { type: String, required: true },
  canAdd: { type: Boolean, default: false },
})

const emit = defineEmits(['add'])
const { t } = useI18n()
</script>

<template>
  <span class="profile-missing" data-testid="profile-missing">
    <AppIcon name="warning-triangle" class="profile-missing__icon" />
    <span class="profile-missing__text">{{ t('portal.profile.missing.text') }}</span>
    <button
      v-if="canAdd"
      type="button"
      class="profile-missing__add"
      :aria-label="t('portal.profile.missing.addLabel', { field: label })"
      @click="emit('add')"
    >
      {{ t('portal.profile.missing.add') }}
    </button>
  </span>
</template>
