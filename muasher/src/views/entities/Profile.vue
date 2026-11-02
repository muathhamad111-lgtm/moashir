<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import Card from '@/components/ui/Card.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ProfileCompletionPrompt from '@/components/entities/ProfileCompletionPrompt.vue'
import EntityDetailsList from '@/components/entities/EntityDetailsList.vue'
import EntityProfileEditor from '@/components/entities/profile/EntityProfileEditor.vue'
import OfficialEmailCard from '@/components/entities/profile/OfficialEmailCard.vue'
import ProfileChangeHistory from '@/components/entities/ProfileChangeHistory.vue'
import { profileChangesService } from '@/services/profileChangesService'
import { useEntityStore } from '@/stores/entity'
import { useRegions } from '@/composables/useRegions'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { usePermissions } from '@/composables/usePermissions'
import { useToast } from '@/composables/useToast'
import { PERMISSIONS } from '@/constants/permissionKeys'

const PROFILE_ROWS = [
  'reference',
  'nameAr',
  'nameEn',
  'authorizedPersonName',
  'phone',
  'entityType',
  'developmentSector',
  'orgSize',
  'foundingYear',
  'region',
  'city',
  'governanceGrade',
  'employeesCount',
  'annualBeneficiariesCount',
  'hasStrategicPlan',
  'marketingConsent',
  'createdAt',
]

const { t } = useI18n()
const route = useRoute()
const entityStore = useEntityStore()
const regions = useRegions()
const { rowsOf, localized } = useEntityDisplay()
const { can } = usePermissions()
const toast = useToast()

const status = ref('loading')
const errorMessage = ref('')
const editing = ref(false)
const profile = computed(() => entityStore.profile)
const historyKey = computed(() => `${profile.value?.id ?? 'none'}-${editing.value ? 'editing' : 'viewing'}`)
const rows = computed(() => (profile.value ? rowsOf(profile.value, PROFILE_ROWS) : []))
const canEdit = computed(() => can(PERMISSIONS.ENTITY_PROFILE_EDIT))
const loadHistory = (params) => profileChangesService.listMine(params)

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
  <section class="entity-profile">
    <div v-if="status === 'loading'" class="entity-profile__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="6" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />

    <template v-else-if="profile">
      <header class="entity-profile__header">
        <div class="entity-profile__heading">
          <h1 class="entity-profile__title">{{ localized(profile.name) }}</h1>
          <StatusBadge :status="profile.status" />
        </div>
        <BaseButton v-if="canEdit && !editing" variant="secondary" icon="fa-solid fa-pen" @click="editing = true">
          {{ t('entities.profile.edit') }}
        </BaseButton>
      </header>

      <ProfileCompletionPrompt
        v-if="!profile.profileCompleted && !editing"
        :welcome="route.query.welcome === '1'"
        @complete="editing = true"
      />

      <Card v-if="editing" :title="t('entities.profile.editTitle')">
        <EntityProfileEditor
          :profile="profile"
          :region-options="regions.regionOptions.value"
          :city-options-for="regions.cityOptionsFor"
          @saved="editing = false"
          @cancel="editing = false"
        />
      </Card>

      <Card v-else :title="t('entities.profile.detailsTitle')">
        <EntityDetailsList :rows="rows" />
      </Card>

      <OfficialEmailCard :profile="profile" @changed="refresh" />
      <ProfileChangeHistory :key="historyKey" :loader="loadHistory" />
    </template>
  </section>
</template>
