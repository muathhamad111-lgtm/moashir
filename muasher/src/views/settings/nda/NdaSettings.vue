<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import { RouterLink } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import NdaVersionsPanel from '@/components/nda/admin/NdaVersionsPanel.vue'
import NdaScopeCard from '@/components/nda/admin/NdaScopeCard.vue'
import NdaPublishDialog from '@/components/nda/admin/NdaPublishDialog.vue'
import { useNdaOverview } from '@/composables/useNdaOverview'
import { success } from '@/composables/useSuccess'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const { overview, summary, status, errorMessage, load } = useNdaOverview()
const versionsPanel = ref(null)
const publishOpen = ref(false)
const publishing = ref(null)

function openPublish(draft) {
  publishing.value = draft
  publishOpen.value = true
}

async function onPublished({ version, reached }) {
  success.show({
    title: t('nda.admin.publish.done', { version: version.version }),
    message: t('nda.admin.publish.doneMessage', { count: reached }),
  })
  await Promise.all([load(), versionsPanel.value?.reload()])
}

useHead({ title: () => t('nda.admin.title') })
</script>

<template>
  <section class="nda-admin">
    <header class="nda-admin__header">
      <div>
        <h1 class="settings-hub__title">{{ t('nda.admin.title') }}</h1>
        <p class="settings-hub__subtitle">{{ t('nda.admin.subtitle') }}</p>
      </div>
      <div class="nda-admin__actions">
        <RouterLink
          :to="{ name: ROUTE_NAMES.ADMIN_SETTINGS_NDA_ACCEPTANCES }"
          class="btn btn--secondary"
        >
          {{ t('nda.admin.registerLink') }}
        </RouterLink>
        <BaseButton
          v-if="versionsPanel?.draft"
          variant="primary"
          icon="check-circle"
          data-testid="nda-open-publish"
          @click="openPublish(versionsPanel.draft)"
        >
          {{ t('nda.admin.publishDraft') }}
        </BaseButton>
      </div>
    </header>

    <Skeleton v-if="status === 'loading'" variant="text" :count="6" />
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
    <template v-else>
      <NdaVersionsPanel
        ref="versionsPanel"
        :in-scope="summary?.inScope ?? null"
        @publish="openPublish"
      />
      <NdaScopeCard :overview="overview" @saved="overview = $event" />
    </template>

    <NdaPublishDialog v-model="publishOpen" :draft="publishing" @published="onPublished" />
  </section>
</template>
