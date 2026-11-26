<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EntityDocumentPicker from '@/components/entities/documents/EntityDocumentPicker.vue'
import { useOwnEntityDocuments } from '@/composables/useOwnEntityDocuments'
import { ENTITY_DOCUMENTS, ENTITY_DOCUMENT_RULES } from '@/constants/entityDocuments'

const props = defineProps({
  attached: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const { t } = useI18n()
const own = useOwnEntityDocuments()
const key = ENTITY_DOCUMENTS.AUTHORIZATION_LETTER
const message = computed(() => props.error || own.errors[key] || '')
</script>

<template>
  <div class="authorization-letter field field--required" data-testid="authorization-letter-field">
    <span class="field__label">{{ t('entities.documents.kinds.authorizationLetter') }}</span>
    <p class="authorization-letter__note">
      {{ t('entities.documents.letterNote', { max: ENTITY_DOCUMENT_RULES.maxMb }) }}
    </p>
    <EntityDocumentPicker
      name="authorizationLetter"
      :label="attached ? t('entities.documents.replace') : t('entities.documents.upload')"
      :loading="Boolean(own.busy[key])"
      @select="own.upload(key, $event)"
    />
    <span v-if="attached" class="entity-documents__status" role="status">
      {{ t('entities.documents.letterAttached') }}
    </span>
    <p v-if="message" class="field__error" role="alert">{{ message }}</p>
  </div>
</template>
