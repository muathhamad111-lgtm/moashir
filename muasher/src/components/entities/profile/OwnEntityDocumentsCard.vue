<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import EntityDocumentList from '@/components/entities/documents/EntityDocumentList.vue'
import EntityDocumentPicker from '@/components/entities/documents/EntityDocumentPicker.vue'
import { useOwnEntityDocuments } from '@/composables/useOwnEntityDocuments'
import { ENTITY_DOCUMENT_RULES, REQUIRED_ENTITY_DOCUMENTS } from '@/constants/entityDocuments'

defineProps({
  canEdit: { type: Boolean, default: false },
})

const { t } = useI18n()
const own = useOwnEntityDocuments()
</script>

<template>
  <section
    id="entity-documents"
    class="profile-section entity-documents-card"
    :aria-label="t('entities.documents.title')"
    data-testid="own-entity-documents"
  >
    <header class="profile-section__header">
      <div class="profile-section__heading">
        <span class="profile-section__icon" aria-hidden="true"><AppIcon name="docs" /></span>
        <h2 class="profile-section__title">{{ t('entities.documents.title') }}</h2>
      </div>
    </header>
    <p class="entity-documents__facts">
      {{ t('entities.documents.hint', { max: ENTITY_DOCUMENT_RULES.maxMb }) }}
    </p>
    <EntityDocumentList
      :documents="own.documents.value"
      :keys="REQUIRED_ENTITY_DOCUMENTS"
      :request-link="own.requestLink"
    >
      <template #status="{ row }">
        <span v-if="own.isPending(row.key)" class="entity-documents__status" role="status">
          {{ t('entities.documents.pending') }}
        </span>
        <span v-if="own.errors[row.key]" class="field__error" role="alert">
          {{ own.errors[row.key] }}
        </span>
      </template>
      <template v-if="canEdit" #actions="{ row }">
        <EntityDocumentPicker
          :name="row.key"
          :label="row.file ? t('entities.documents.replace') : t('entities.documents.upload')"
          :loading="Boolean(own.busy[row.key])"
          @select="own.upload(row.key, $event)"
        />
      </template>
    </EntityDocumentList>
  </section>
</template>
