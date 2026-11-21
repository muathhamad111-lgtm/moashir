<script setup>
import { useI18n } from 'vue-i18n'
import ProfileMissingMarker from './ProfileMissingMarker.vue'

defineProps({
  label: { type: String, required: true },
  value: { type: String, default: '' },
  ltr: { type: Boolean, default: false },
  missing: { type: Boolean, default: false },
  canAdd: { type: Boolean, default: false },
  wide: { type: Boolean, default: false },
})

const emit = defineEmits(['add'])
const { t } = useI18n()
</script>

<template>
  <div class="profile-field" :class="{ 'profile-field--wide': wide }">
    <dt class="profile-field__label">{{ label }}</dt>
    <dd class="profile-field__value">
      <ProfileMissingMarker v-if="missing" :label="label" :can-add="canAdd" @add="emit('add')" />
      <bdi v-else-if="value" :dir="ltr ? 'ltr' : 'auto'">{{ value }}</bdi>
      <span v-else class="profile-field__empty">
        <span aria-hidden="true">{{ t('portal.profile.values.empty') }}</span>
        <span class="profile-field__sr">{{ t('portal.profile.values.emptyLabel') }}</span>
      </span>
      <slot />
    </dd>
  </div>
</template>
