<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import Tabs from '@/components/ui/Tabs.vue'
import CertificateEditor from '@/components/certificates/CertificateEditor.vue'
import { useSettings, CERTIFICATE_KINDS } from '@/composables/useSettings'
import { useToast } from '@/composables/useToast'
import { settingsService } from '@/services/settingsService'

const { t } = useI18n()
const { certificateKindLabel } = useSettings()
const toast = useToast()

const templates = ref([])
const status = ref('loading')
const errorMessage = ref('')
const activeKind = ref(CERTIFICATE_KINDS[0])
const savingKind = ref('')

async function load() {
  status.value = 'loading'
  errorMessage.value = ''
  try {
    const envelope = await settingsService.getCertificates()
    templates.value = envelope.data ?? []
    status.value = 'success'
  } catch (error) {
    errorMessage.value = error?.normalized?.message || t('common.error')
    status.value = 'error'
  }
}

onMounted(load)

const tabs = computed(() =>
  CERTIFICATE_KINDS.map((kind) => ({ value: kind, label: certificateKindLabel(kind) })),
)

const activeConfig = computed(() => templates.value.find((item) => item.kind === activeKind.value))

async function saveTemplate(payload) {
  savingKind.value = activeKind.value
  try {
    const envelope = await settingsService.updateCertificate(activeKind.value, payload)
    templates.value = templates.value.map((item) =>
      item.kind === activeKind.value ? envelope.data : item,
    )
    toast.success(t('settings.toasts.saved'))
  } catch (error) {
    toast.error(error?.normalized?.message || t('settings.toasts.saveFailed'))
  } finally {
    savingKind.value = ''
  }
}
</script>

<template>
  <SettingsSection
    :title="t('settings.certificates.title')"
    :description="t('settings.certificates.description')"
    :status="status"
    :error-message="errorMessage"
    @retry="load"
  >
    <Tabs v-model="activeKind" :tabs="tabs">
      <CertificateEditor
        v-if="activeConfig"
        :config="activeConfig"
        :saving="savingKind === activeKind"
        @save="saveTemplate"
      />
    </Tabs>
  </SettingsSection>
</template>
