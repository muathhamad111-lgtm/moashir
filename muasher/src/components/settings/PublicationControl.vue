<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  published: { type: Boolean, required: true },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['change'])
const { t } = useI18n()
</script>

<template>
  <div class="publication-control" :class="{ 'publication-control--off': !published }" role="status">
    <div class="publication-control__state">
      <Badge :variant="published ? 'success' : 'warning'">
        {{ published ? t('settings.homepage.publication.published') : t('settings.homepage.publication.unpublished') }}
      </Badge>
      <p class="publication-control__hint">
        {{ published ? t('settings.homepage.publication.publishedHint') : t('settings.homepage.publication.unpublishedHint') }}
      </p>
    </div>
    <BaseButton
      :variant="published ? 'ghost' : 'primary'"
      :icon="published ? 'fa-solid fa-eye-slash' : 'fa-solid fa-globe'"
      :loading="busy"
      :disabled="busy"
      @click="emit('change', !published)"
    >
      {{ published ? t('settings.homepage.publication.unpublish') : t('settings.homepage.publication.publish') }}
    </BaseButton>
  </div>
</template>
