<script setup>
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import LegalPreview from './LegalPreview.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const LOCALES = ['ar', 'en']
const BODY_ROWS = 16

defineProps({
  values: { type: Object, required: true },
  isNew: { type: Boolean, default: false },
  busy: { type: String, default: '' },
})

const emit = defineEmits(['save', 'publish', 'discard', 'close'])
const { t } = useI18n()
</script>

<template>
  <form class="legal-editor" novalidate @submit.prevent="emit('save')">
    <p class="legal-editor__note" role="note">
      <AppIcon name="info-circle" />
      {{ t('legalAdmin.editor.formatNote') }}
    </p>
    <FieldInput
      name="version"
      :label="t('legalAdmin.editor.version')"
      :hint="t('legalAdmin.editor.versionHint')"
      input-dir="ltr"
      required
    />
    <div v-for="locale in LOCALES" :key="locale" class="legal-editor__language">
      <div class="legal-editor__fields">
        <FieldInput
          :name="`title.${locale}`"
          :label="t(`legalAdmin.editor.title.${locale}`)"
          :input-dir="locale === 'ar' ? 'rtl' : 'ltr'"
          required
        />
        <FieldTextarea
          :name="`body.${locale}`"
          :label="t(`legalAdmin.editor.body.${locale}`)"
          :hint="t('legalAdmin.editor.bodyHint')"
          :rows="BODY_ROWS"
          required
        />
      </div>
      <LegalPreview
        :locale="locale"
        :title="values.title?.[locale] ?? ''"
        :body="values.body?.[locale] ?? ''"
      />
    </div>
    <div class="legal-editor__actions">
      <BaseButton variant="ghost" icon="arrow-left" @click="emit('close')">{{
        t('legalAdmin.backToList')
      }}</BaseButton>
      <BaseButton
        v-if="!isNew"
        variant="danger"
        icon="trash"
        :loading="busy === 'discard'"
        :disabled="Boolean(busy)"
        @click="emit('discard')"
        >{{ t('legalAdmin.discard') }}</BaseButton
      >
      <BaseButton
        type="submit"
        variant="secondary"
        icon="save"
        :loading="busy === 'save'"
        :disabled="Boolean(busy)"
        >{{ isNew ? t('legalAdmin.createDraft') : t('legalAdmin.saveDraft') }}</BaseButton
      >
      <BaseButton
        v-if="!isNew"
        variant="primary"
        icon="check-circle"
        :loading="busy === 'publish'"
        :disabled="Boolean(busy)"
        @click="emit('publish')"
        >{{ t('legalAdmin.publish') }}</BaseButton
      >
    </div>
  </form>
</template>
