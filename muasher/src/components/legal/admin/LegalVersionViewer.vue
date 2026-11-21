<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import LegalPreview from './LegalPreview.vue'
import LegalVersionStatus from './LegalVersionStatus.vue'
import { formatRiyadhDateTime } from '@/utils/datetime'
import AppIcon from '@/components/ui/AppIcon.vue'

const LOCALES = ['ar', 'en']

defineProps({
  version: { type: Object, required: true },
})

const emit = defineEmits(['close'])
const { t, locale } = useI18n()
</script>

<template>
  <div class="legal-viewer">
    <p class="legal-viewer__meta">
      <bdi dir="ltr">{{ version.version }}</bdi>
      <LegalVersionStatus :status="version.status" />
      <span v-if="version.publishedAt">
        {{ t('legalAdmin.publishedOn') }}
        <bdi>{{ formatRiyadhDateTime(version.publishedAt, locale) }}</bdi>
      </span>
    </p>
    <p class="legal-editor__note" role="note">
      <AppIcon name="lock" />
      {{ t('legalAdmin.readOnly') }}
    </p>
    <div class="legal-viewer__languages">
      <LegalPreview
        v-for="language in LOCALES"
        :key="language"
        :locale="language"
        :title="version.title[language]"
        :body="version.body?.[language] ?? ''"
      />
    </div>
    <BaseButton variant="ghost" icon="arrow-left" @click="emit('close')">{{
      t('legalAdmin.backToList')
    }}</BaseButton>
  </div>
</template>
