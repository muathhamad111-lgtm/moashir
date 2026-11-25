<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import EntityDocumentList from '@/components/entities/documents/EntityDocumentList.vue'
import { entitiesService } from '@/services/entitiesService'
import { toEntityDocuments } from '@/services/mappers/entityDocuments'
import { STAFF_ENTITY_DOCUMENTS } from '@/constants/entityDocuments'

const props = defineProps({
  entity: { type: Object, required: true },
})

const { t } = useI18n()
const documents = computed(() => toEntityDocuments(props.entity.documents))

function requestLink(document) {
  return entitiesService.requestDocumentDownload(props.entity.id, document)
}
</script>

<template>
  <Card :title="t('entities.documents.title')" class="entity-documents-card">
    <EntityDocumentList
      :documents="documents"
      :keys="STAFF_ENTITY_DOCUMENTS"
      :request-link="requestLink"
    />
  </Card>
</template>
