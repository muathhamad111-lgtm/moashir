<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, RouterLink } from 'vue-router'
import { useHead } from '@unhead/vue'
import PublicCycleCard from '@/components/award/PublicCycleCard.vue'
import AdvancedEntityPanel from '@/components/award/AdvancedEntityPanel.vue'
import AdvancedIntroContent from '@/components/award/AdvancedIntroContent.vue'
import { useAdvancedIntro } from '@/composables/useAdvancedIntro'
import { useDraftDeletion } from '@/composables/useDraftDeletion'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const router = useRouter()
const intro = useAdvancedIntro()
const { cycleStatus, status, errorMessage, cycle, current, applying, isAuthenticated, isEntity } = intro
const { deleting, deleteDraft } = useDraftDeletion()

useHead({ title: () => t('advanced.title') })

async function onApply() {
  const request = await intro.apply()
  if (request) router.push({ name: ROUTE_NAMES.ASSESSMENT_ADVANCED_FORM, params: { id: request.id } })
}

async function onDelete(request) {
  if (await deleteDraft(request)) intro.loadCurrent()
}

onMounted(intro.load)
</script>

<template>
  <section class="advanced-intro">
    <header class="advanced-intro__header">
      <p class="advanced-intro__eyebrow">{{ t('advanced.eyebrow') }}</p>
      <h1 class="advanced-intro__title">{{ t('advanced.title') }}</h1>
      <p class="advanced-intro__subtitle">{{ t('advanced.subtitle') }}</p>
    </header>
    <div class="advanced-intro__layout">
      <PublicCycleCard :status="cycleStatus" :cycle="cycle" @retry="intro.load" />
      <AdvancedEntityPanel
        v-if="isEntity"
        :status="status"
        :current="current"
        :error-message="errorMessage"
        :applying="applying"
        :deleting="deleting"
        @apply="onApply"
        @delete="onDelete"
        @retry="intro.loadCurrent"
      />
      <aside v-else-if="!isAuthenticated" class="advanced-intro__guest">
        <p class="advanced-intro__guest-text">{{ t('advanced.guest.message') }}</p>
        <RouterLink class="btn btn--primary btn--md" :to="{ name: ROUTE_NAMES.AUTH_LOGIN, query: { redirect: '/assessment/advanced' } }">
          <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
          {{ t('advanced.guest.signIn') }}
        </RouterLink>
      </aside>
    </div>
    <AdvancedIntroContent />
  </section>
</template>
