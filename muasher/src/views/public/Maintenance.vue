<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { useSiteStatusStore } from '@/stores/siteStatus'
import Skeleton from '@/components/feedback/Skeleton.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const { t } = useI18n()
const siteStatus = useSiteStatusStore()
const { maintenance, status } = storeToRefs(siteStatus)

onMounted(() => siteStatus.refresh())
</script>

<template>
  <section class="maintenance" aria-labelledby="maintenance-title">
    <span class="maintenance__icon" aria-hidden="true">
      <i class="fa-solid fa-screwdriver-wrench"></i>
    </span>

    <div v-if="status === 'loading'" class="maintenance__loading" role="status">
      <Skeleton variant="text" :count="2" />
    </div>

    <template v-else-if="maintenance">
      <h1 id="maintenance-title" class="maintenance__title">{{ t('maintenance.title') }}</h1>
      <p class="maintenance__message">{{ t('maintenance.message') }}</p>
      <p class="maintenance__hint">{{ t('maintenance.staffHint') }}</p>
      <div class="maintenance__actions">
        <router-link class="btn btn--primary" :to="{ name: ROUTE_NAMES.AUTH_LOGIN }">
          <i class="fa-solid fa-right-to-bracket u-flip-in-rtl" aria-hidden="true"></i>
          {{ t('maintenance.staffLogin') }}
        </router-link>
        <BaseButton variant="ghost" icon="fa-solid fa-rotate" @click="siteStatus.refresh()">
          {{ t('maintenance.retry') }}
        </BaseButton>
      </div>
    </template>

    <template v-else>
      <h1 id="maintenance-title" class="maintenance__title">{{ t('maintenance.available') }}</h1>
      <div class="maintenance__actions">
        <router-link class="btn btn--primary" :to="{ name: ROUTE_NAMES.PUBLIC_HOME }">
          <i class="fa-solid fa-house" aria-hidden="true"></i>
          {{ t('maintenance.backHome') }}
        </router-link>
      </div>
    </template>
  </section>
</template>
