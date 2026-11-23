<script setup>
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import BaseModal from '@/components/modal/BaseModal.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import NdaAcceptanceList from '@/components/nda/NdaAcceptanceList.vue'
import NdaAgreementText from '@/components/identity/nda/NdaAgreementText.vue'
import { ndaService } from '@/services/ndaService'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const toast = useToast()
const acceptances = ref([])
const status = ref('loading')
const errorMessage = ref('')
const viewing = ref(null)
const viewerOpen = ref(false)

async function load() {
  status.value = 'loading'
  try {
    acceptances.value = (await ndaService.myAcceptances()).data
    status.value = 'success'
  } catch (error) {
    errorMessage.value = error?.normalized?.message ?? ''
    status.value = 'error'
  }
}

async function view(acceptance) {
  viewing.value = null
  viewerOpen.value = true
  try {
    viewing.value = (await ndaService.myVersion(acceptance.termsVersionId)).data
  } catch (error) {
    viewerOpen.value = false
    toast.error(error?.normalized?.message || t('errors.unexpected'))
  }
}

onMounted(load)
// Accepting in the agreement modal adds a row here without leaving the page.
watch(
  () => useAuthStore().user?.ndaAcceptanceRequired,
  (required, before) => {
    if (before === true && required === false) load()
  },
)
</script>

<template>
  <Card :title="t('nda.account.title')" data-testid="my-agreements">
    <p class="account-page__muted">{{ t('nda.account.hint') }}</p>
    <NdaAcceptanceList
      :acceptances="acceptances"
      :status="status"
      :error-message="errorMessage"
      @retry="load"
      @view="view"
    />
    <BaseModal v-model="viewerOpen" :title="t('nda.modal.title')" size="lg">
      <NdaAgreementText v-if="viewing" :agreement="viewing" />
      <Skeleton v-else variant="text" :count="6" />
    </BaseModal>
  </Card>
</template>
