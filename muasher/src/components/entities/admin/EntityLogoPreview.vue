<script setup>
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import { useSignedImageLink } from '@/composables/useSignedImageLink'
import { isoDate } from '@/utils/datetime'
import AppIcon from '@/components/ui/AppIcon.vue'

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
  <Card :title="t('entities.record.logo.title')" class="entity-logo-preview">
    <div class="entity-logo-preview__frame">
      <img
        v-if="logo?.url && !failed"
        class="entity-logo-preview__image"
        :src="logo.url"
        :alt="t('entities.record.logo.alt', { name: entityName })"
        @error="onImageError"
      />
      <p v-else-if="logo" class="entity-logo-preview__empty" role="status">
        <AppIcon name="image-off" />
        {{ t('entities.record.logo.unavailable') }}
      </p>
      <p v-else class="entity-logo-preview__empty">
        <AppIcon name="image" />
        {{ t('entities.record.logo.none') }}
      </p>
    </div>
    <p v-if="logo?.uploadedAt" class="entity-logo-preview__meta">
      {{ t('entities.record.logo.uploadedOn') }}
      <bdi dir="ltr">{{ isoDate(logo.uploadedAt) }}</bdi>
    </p>
    <p class="entity-logo-preview__note">
      <AppIcon name="award" />
      {{ t('entities.record.logo.note') }}
    </p>
  </Card>
</template>
