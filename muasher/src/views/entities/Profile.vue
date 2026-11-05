<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useHead } from '@unhead/vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EntityProfileWorkspace from '@/components/entities/profile/portal/EntityProfileWorkspace.vue'
import { useEntityStore } from '@/stores/entity'
import { useRegions } from '@/composables/useRegions'
import { usePermissions } from '@/composables/usePermissions'
import { useToast } from '@/composables/useToast'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ENTITY_STATUSES } from '@/constants/entityEnums'

const { t } = useI18n()
const route = useRoute()
const entityStore = useEntityStore()
const regions = useRegions()
const { can } = usePermissions()
const toast = useToast()

const status = ref('loading')
const errorMessage = ref('')
const profile = computed(() => entityStore.profile)
const canEdit = computed(
  () => can(PERMISSIONS.ENTITY_PROFILE_EDIT) && profile.value?.status === ENTITY_STATUSES.ACTIVE,
)

useHead({ title: () => t('portal.profile.eyebrowShort') })

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
  <div v-if="status === 'loading'" class="entity-profile-hub__loading" aria-busy="true">
    <Skeleton variant="block" />
    <Skeleton variant="text" :count="6" />
  </div>
  <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
  <EntityProfileWorkspace
    v-else-if="profile"
    :profile="profile"
    :can-edit="canEdit"
    :welcome="route.query.welcome === '1'"
    :region-options="regions.regionOptions.value"
    :city-options-for="regions.cityOptionsFor"
    @refresh="refresh"
  />
</template>
