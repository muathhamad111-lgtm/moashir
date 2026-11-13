<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useHead } from '@unhead/vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EntityProfileView from '@/components/entities/profile/EntityProfileView.vue'
import { useEntityStore } from '@/stores/entity'
import { useRegions } from '@/composables/useRegions'
import { useEntityLevel } from '@/composables/useEntityLevel'
import { useToast } from '@/composables/useToast'

const { t } = useI18n()
const route = useRoute()
const entityStore = useEntityStore()
const regions = useRegions()
const { certificate, tier } = useEntityLevel()
const toast = useToast()

const status = ref('loading')
const errorMessage = ref('')
const profile = computed(() => entityStore.profile)

useHead({ title: () => t('portal.profile.documentTitle') })

async function load() {
  status.value = 'loading'
  errorMessage.value = ''
  try {
    await Promise.all([entityStore.loadProfile({ force: true }), regions.load()])
    status.value = regions.status.value === 'error' ? 'error' : 'success'
  } catch (error) {
    errorMessage.value = error?.normalized?.message ?? ''
    status.value = 'error'
  }
}

async function refresh() {
  try {
    await entityStore.loadProfile({ force: true })
  } catch {
    toast.error(t('entities.profile.refreshFailed'))
  }
}

onMounted(load)
</script>

<template>
  <div v-if="status === 'loading'" class="entity-profile__loading" aria-busy="true">
    <Skeleton variant="block" />
    <Skeleton variant="text" :count="6" />
  </div>
  <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
  <EntityProfileView
    v-else-if="profile"
    :profile="profile"
    :tier="tier"
    :certificate="certificate"
    :welcome="route.query.welcome === '1'"
    :region-options="regions.regionOptions.value"
    :city-options-for="regions.cityOptionsFor"
    @refresh="refresh"
  />
</template>
