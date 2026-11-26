<script setup>
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useSignedImageLink } from '@/composables/useSignedImageLink'

// The entity's logo in the record header, read-only: the entity changes it from its own account.
const props = defineProps({
  logo: { type: Object, default: null },
  entityName: { type: String, required: true },
  refresh: { type: Function, required: true },
})

const { t } = useI18n()
const { failed, markLoaded, onImageError } = useSignedImageLink(() => props.refresh())

watch(
  () => props.logo?.url,
  () => markLoaded(),
)
</script>

<template>
  <div class="entity-head-logo" data-testid="entity-head-logo">
    <img
      v-if="logo?.url && !failed"
      class="entity-head-logo__image"
      :src="logo.url"
      :alt="t('entities.record.logo.alt', { name: entityName })"
      @error="onImageError"
    />
    <AppIcon
      v-else
      name="building"
      class="entity-head-logo__empty"
      role="img"
      :aria-label="t(logo ? 'entities.record.logo.unavailable' : 'entities.record.logo.none')"
    />
  </div>
</template>
