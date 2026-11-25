<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  missing: { type: Array, required: true },
})

const { t } = useI18n()
const names = computed(() =>
  props.missing.map((key) => t(`entities.documents.kinds.${key}`)).join(t('common.listSeparator')),
)
</script>

<template>
  <aside class="documents-notice" role="note" data-testid="documents-notice">
    <AppIcon name="warning-triangle" class="documents-notice__icon" />
    <div class="documents-notice__text">
      <span class="documents-notice__title">{{ t('entities.documents.notice.title') }}</span>
      <span>{{ t('entities.documents.notice.body', { documents: names }) }}</span>
      <a class="documents-notice__link" href="#entity-documents">
        {{ t('entities.documents.notice.action') }}
      </a>
    </div>
  </aside>
</template>
