<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import NotificationCatalogueFilters from '@/components/settings/NotificationCatalogueFilters.vue'
import NotificationCatalogueTable from '@/components/settings/NotificationCatalogueTable.vue'
import { useNotificationCatalogue } from '@/composables/useNotificationCatalogue'
import { formatNumber } from '@/utils/formatNumber'

const { t, n } = useI18n()
const { filters, types, total, status, errorMessage, load } = useNotificationCatalogue()

const sectionStatus = computed(() => (status.value === 'forbidden' ? 'forbidden' : 'success'))
const totalText = computed(() =>
  t('settings.notifications.catalogue.total', { count: formatNumber(n, total.value) }),
)

onMounted(load)
</script>

<template>
  <SettingsSection
    :title="t('settings.notifications.title')"
    :description="t('settings.notifications.description')"
    :status="sectionStatus"
    :error-message="errorMessage"
    @retry="load"
  >
    <div class="notification-catalogue">
      <NotificationCatalogueFilters
        :model-value="filters"
        @update:model-value="Object.assign(filters, $event)"
      />
      <p v-if="status === 'success'" class="notification-catalogue__total" aria-live="polite">
        {{ totalText }}
      </p>
      <NotificationCatalogueTable
        :types="types"
        :status="status === 'forbidden' ? 'error' : status"
        :error-message="errorMessage"
        @retry="load"
      />
    </div>
  </SettingsSection>
</template>
