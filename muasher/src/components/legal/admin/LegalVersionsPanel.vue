<script setup>
import { onMounted, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import LegalVersionList from './LegalVersionList.vue'
import LegalDraftEditor from './LegalDraftEditor.vue'
import LegalVersionViewer from './LegalVersionViewer.vue'
import { useLegalVersions } from '@/composables/useLegalVersions'

const props = defineProps({
  kind: { type: String, required: true },
})

const { t } = useI18n()
const legal = useLegalVersions(toRef(props, 'kind'))
const { MODES, versions, status, errorMessage, selected, mode, busy, form, draft, reasonPrompt } =
  legal

onMounted(legal.loadList)
</script>

<template>
  <section class="legal-admin" :aria-labelledby="`legal-admin-${kind}`" :data-legal-kind="kind">
    <header class="legal-admin__header">
      <div>
        <h2 :id="`legal-admin-${kind}`" class="legal-admin__title">
          {{ t(`legalAdmin.kinds.${kind}`) }}
        </h2>
        <p class="legal-admin__desc">{{ t('legalAdmin.description') }}</p>
      </div>
      <template v-if="mode === MODES.LIST && status === 'success'">
        <BaseButton v-if="draft" variant="primary" icon="pen-line" @click="legal.open(draft)">
          {{ t('legalAdmin.continueDraft') }}
        </BaseButton>
        <BaseButton v-else variant="primary" icon="plus" @click="legal.startDraft">
          {{ t('legalAdmin.newDraft') }}
        </BaseButton>
      </template>
    </header>

    <LegalVersionList
      v-if="mode === MODES.LIST"
      :versions="versions"
      :status="status"
      :error-message="errorMessage"
      :busy="busy"
      @open="legal.open"
      @retry="legal.loadList"
    />
    <LegalDraftEditor
      v-else-if="mode === MODES.EDIT || mode === MODES.NEW"
      :values="form.values"
      :is-new="mode === MODES.NEW"
      :busy="busy"
      @save="legal.save"
      @publish="legal.publish"
      @discard="legal.discard"
      @close="legal.close"
    />
    <LegalVersionViewer v-else-if="selected" :version="selected" @close="legal.close" />

    <ReasonDialog
      v-model="reasonPrompt.open.value"
      :title="t('review.reasonTitle')"
      :submit-label="t('actions.confirm')"
      :action="reasonPrompt.provide"
      action-name="legalAdmin.superAdminReason"
    />
  </section>
</template>
