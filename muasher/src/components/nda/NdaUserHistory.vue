<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import NdaAcceptanceList from './NdaAcceptanceList.vue'
import { ndaService } from '@/services/ndaService'

const props = defineProps({
  user: { type: Object, required: true },
})

const { t } = useI18n()
const acceptances = ref([])
const status = ref('loading')
const errorMessage = ref('')

async function load() {
  status.value = 'loading'
  try {
    acceptances.value = (await ndaService.userAcceptances(props.user.id)).data
    status.value = 'success'
  } catch (error) {
    errorMessage.value = error?.normalized?.message ?? ''
    status.value = 'error'
  }
}

watch(() => props.user.id, load, { immediate: true })
</script>

<template>
  <section class="nda-admin" aria-labelledby="nda-user-history" data-testid="nda-user-history">
    <h3 id="nda-user-history" class="legal-admin__title">{{ t('nda.history.title') }}</h3>
    <p v-if="user.ndaStatus" class="nda-admin__note">
      {{ t(`nda.history.status.${user.ndaStatus}`) }}
    </p>
    <NdaAcceptanceList
      :acceptances="acceptances"
      :status="status"
      :error-message="errorMessage"
      show-roles
      @retry="load"
    />
  </section>
</template>
