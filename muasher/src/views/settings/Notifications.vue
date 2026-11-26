<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import AppMenu from '@/components/ui/AppMenu.vue'
import NotificationCatalogueFilters from '@/components/settings/NotificationCatalogueFilters.vue'
import NotificationViewSwitch from '@/components/settings/notifications/NotificationViewSwitch.vue'
import NotificationMatrixView from '@/components/settings/notifications/NotificationMatrixView.vue'
import NotificationChannelView from '@/components/settings/notifications/NotificationChannelView.vue'
import { useNotificationCatalogue } from '@/composables/useNotificationCatalogue'
import { useNotificationCatalogueView } from '@/composables/useNotificationCatalogueView'
import { useNotificationSwitchboard } from '@/composables/useNotificationSwitchboard'
import {
  NOTIFICATION_LAYOUTS,
  useNotificationCatalogueLayout,
} from '@/composables/useNotificationCatalogueLayout'
import { useConfirm } from '@/composables/useConfirm'
import { NOTIFICATION_CHANNELS } from '@/constants/notificationCatalogue'
import { MENU_TRIGGERS } from '@/constants/dropdown'
import { notificationTypeName } from '@/utils/notificationTypeName'

const { t, te } = useI18n()
const { confirm } = useConfirm()
const { types, status, errorMessage, load, replace } = useNotificationCatalogue()
const { filters, visible, groups } = useNotificationCatalogueView(types, (code) =>
  notificationTypeName(code, t, te),
)
const board = useNotificationSwitchboard(types, replace)

const view = useNotificationCatalogueLayout()
const isFiltered = computed(() => Boolean(filters.search || filters.audience))

const bulkActions = computed(() =>
  ['all', ...NOTIFICATION_CHANNELS].flatMap((scope) =>
    ['enable', 'disable'].map((action) => ({
      key: `${action}:${scope}`,
      label: t(`settings.notifications.catalogue.bulk.${action}Scope`, {
        scope: t(`settings.notifications.catalogue.channelTabs.${scope}`),
      }),
    })),
  ),
)

async function runBulk(change) {
  const action = change.enabled ? 'enable' : 'disable'
  const accepted = await confirm({
    title: t(`settings.notifications.catalogue.bulk.${action}`),
    message: t('settings.notifications.catalogue.bulk.confirm', { count: change.codes.length }),
    confirmLabel: t(`settings.notifications.catalogue.bulk.${action}`),
  })
  if (accepted) await board.switchMany(change)
}

function runMenuAction(key) {
  const [action, scope] = key.split(':')
  runBulk({
    scope: 'codes',
    codes: visible.value.map((type) => type.code),
    ...(scope === 'all' ? {} : { channels: [scope] }),
    enabled: action === 'enable',
  })
}

onMounted(load)
</script>

<template>
  <SettingsSection
    :title="t('settings.notifications.title')"
    :status="status"
    :error-message="errorMessage"
    @retry="load"
  >
    <div class="notification-catalogue">
      <NotificationCatalogueFilters :filters="filters" @change="Object.assign(filters, $event)">
        <template #actions>
          <NotificationViewSwitch v-model="view" />
          <AppMenu
            :actions="bulkActions"
            :label="t('settings.notifications.catalogue.bulk.menu')"
            :trigger="MENU_TRIGGERS.button"
            @select="runMenuAction"
          >
            <template #trigger>{{ t('settings.notifications.catalogue.bulk.menu') }}</template>
          </AppMenu>
        </template>
      </NotificationCatalogueFilters>
      <NotificationMatrixView
        v-if="view === NOTIFICATION_LAYOUTS.byType"
        :groups="groups"
        :board="board"
        :expanded="isFiltered"
        @bulk="runBulk"
      />
      <NotificationChannelView
        v-else
        :groups="groups"
        :types="visible"
        :board="board"
        :expanded="isFiltered"
        @bulk="runBulk"
      />
    </div>
  </SettingsSection>
</template>
