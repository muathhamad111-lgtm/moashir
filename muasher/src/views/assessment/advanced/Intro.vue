<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { useHead } from '@unhead/vue'
import PublicCycleCard from '@/components/award/PublicCycleCard.vue'
import AdvancedIntroContent from '@/components/award/AdvancedIntroContent.vue'
import AwardCycleChooser from '@/components/award/AwardCycleChooser.vue'
import { useAdvancedIntro } from '@/composables/useAdvancedIntro'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const intro = useAdvancedIntro()
const { cycleStatus, displayedCycle, openCycles, showChooser, selectedId, isAuthenticated } = intro

useHead({ title: () => t('advanced.title') })

onMounted(intro.load)
</script>

<template>
  <section class="advanced-intro">
    <header class="advanced-intro__header">
      <p class="advanced-intro__eyebrow">{{ t('advanced.eyebrow') }}</p>
      <h1 class="advanced-intro__title">{{ t('advanced.title') }}</h1>
      <p class="advanced-intro__subtitle">{{ t('advanced.subtitle') }}</p>
    </header>
    <AwardCycleChooser
      v-if="showChooser"
      :choices="openCycles"
      :model-value="selectedId"
      @update:model-value="intro.selectCycle"
    />
    <div class="advanced-intro__layout">
      <PublicCycleCard :status="cycleStatus" :cycle="displayedCycle" @retry="intro.load" />
      <aside v-if="!isAuthenticated" class="advanced-intro__guest">
        <p class="advanced-intro__guest-text">{{ t('advanced.guest.message') }}</p>
        <RouterLink
          class="btn btn--primary btn--md"
          :to="{ name: ROUTE_NAMES.AUTH_LOGIN, query: { redirect: '/assessment/advanced' } }"
        >
          <AppIcon name="login" />
          {{ t('advanced.guest.signIn') }}
        </RouterLink>
      </aside>
    </div>
    <AdvancedIntroContent />
  </section>
</template>
