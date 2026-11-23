<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import LegalDraftEditor from '@/components/legal/admin/LegalDraftEditor.vue'
import LegalVersionViewer from '@/components/legal/admin/LegalVersionViewer.vue'
import NdaVersionsTable from './NdaVersionsTable.vue'
import { useLegalVersions } from '@/composables/useLegalVersions'
import { useToast } from '@/composables/useToast'
import { ndaDraftVersions } from '@/services/ndaService'

defineProps({
  inScope: { type: Number, default: null },
})

const emit = defineEmits(['publish'])

const { t } = useI18n()
const toast = useToast()
const legal = useLegalVersions(ref('nda'), ndaDraftVersions)
const { MODES, versions, status, errorMessage, selected, mode, busy, form, draft } = legal

function requestPublish() {
  if (form.meta.value.dirty) return toast.error(t('legalAdmin.toasts.saveFirst'))
  emit('publish', selected.value)
}

async function reload() {
  legal.close()
  await legal.loadList()
}

onMounted(legal.loadList)
defineExpose({ reload, draft })
</script>

<template>
  <section class="legal-admin" aria-labelledby="nda-versions-title" data-testid="nda-versions">
    <header class="legal-admin__header">
      <h2 id="nda-versions-title" class="legal-admin__title">{{ t('nda.admin.versionsTitle') }}</h2>
      <template v-if="mode === MODES.LIST && status === 'success'">
        <BaseButton v-if="draft" variant="primary" icon="pen-line" @click="legal.open(draft)">
          {{ t('legalAdmin.continueDraft') }}
        </BaseButton>
        <BaseButton v-else variant="primary" icon="plus" @click="legal.startDraft">
          {{ t('nda.admin.newDraft') }}
        </BaseButton>
      </template>
    </header>

    <NdaVersionsTable
      v-if="mode === MODES.LIST"
      :versions="versions"
      :status="status"
      :error-message="errorMessage"
      :busy="busy"
      :in-scope="inScope"
      @open="legal.open"
      @retry="legal.loadList"
    />
    <LegalDraftEditor
      v-else-if="mode === MODES.EDIT || mode === MODES.NEW"
      :values="form.values"
      :is-new="mode === MODES.NEW"
      :busy="busy"
      @save="legal.save"
      @publish="requestPublish"
      @discard="legal.discard"
      @close="legal.close"
    />
    <LegalVersionViewer v-else-if="selected" :version="selected" @close="legal.close" />
    <p class="nda-admin__note">{{ t('nda.admin.immutableNote') }}</p>
  </section>
</template>
