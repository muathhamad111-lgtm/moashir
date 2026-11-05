<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { formatRiyadhDateTime } from '@/utils/datetime'
import { CMS_PAGE_ROUTES } from '@/constants/cms'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  page: { type: Object, required: true },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['publish', 'unpublish', 'details'])
const { t, locale } = useI18n()

const state = computed(() => {
  if (!props.page.published) return { key: 'unpublished', variant: 'warning' }
  return props.page.hasUnpublishedChanges
    ? { key: 'draft', variant: 'info' }
    : { key: 'published', variant: 'success' }
})
const publishedAt = computed(() => formatRiyadhDateTime(props.page.publishedAt, locale.value))
const canPublish = computed(() => !props.page.published || props.page.hasUnpublishedChanges)
</script>

<template>
  <div
    class="publication-control"
    :class="{ 'publication-control--off': !page.published }"
    role="status"
  >
    <div class="publication-control__state">
      <Badge :variant="state.variant">{{ t(`cms.publication.states.${state.key}`) }}</Badge>
      <p class="publication-control__hint">
        {{ t(`cms.publication.hints.${state.key}`) }}
        <span v-if="publishedAt" class="publication-control__date">{{
          t('cms.publication.publishedAt', { date: publishedAt })
        }}</span>
      </p>
    </div>
    <div class="publication-control__actions">
      <RouterLink
        v-if="page.published && CMS_PAGE_ROUTES[page.key]"
        class="btn btn--ghost publication-control__view"
        :to="{ name: CMS_PAGE_ROUTES[page.key] }"
      >
        <AppIcon name="link" />
        {{ t('cms.publication.view') }}
      </RouterLink>
      <BaseButton variant="ghost" icon="gear" :disabled="busy" @click="emit('details')">
        {{ t('cms.page.details') }}
      </BaseButton>
      <BaseButton
        v-if="page.published"
        variant="ghost"
        icon="eye-slash"
        :disabled="busy"
        @click="emit('unpublish')"
      >
        {{ t('cms.publication.unpublish') }}
      </BaseButton>
      <BaseButton
        variant="primary"
        icon="globe"
        :loading="busy"
        :disabled="busy || !canPublish"
        @click="emit('publish')"
      >
        {{ t('cms.publication.publish') }}
      </BaseButton>
    </div>
  </div>
</template>
