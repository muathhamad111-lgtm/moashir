<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import { RouterLink } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatsCards from '@/components/data-list/StatsCards.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import NdaRegisterTable from '@/components/nda/admin/NdaRegisterTable.vue'
import { NDA_FILTERS, useNdaRegister } from '@/composables/useNdaRegister'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const register = useNdaRegister()
const { filter, page, rows, summary, version, lastPage, status, errorMessage, busy } = register

const stats = computed(() => [
  { key: 'accepted', label: t('nda.register.stats.accepted'), value: summary.value.accepted },
  { key: 'pending', label: t('nda.register.stats.pending'), value: summary.value.pending },
  { key: 'version', label: t('nda.register.stats.version'), value: version.value?.version ?? '—' },
])

onMounted(register.load)
useHead({ title: () => t('nda.register.title') })
</script>

<template>
  <section class="nda-admin" data-testid="nda-register">
    <header class="nda-admin__header">
      <div>
        <h1 class="settings-hub__title">{{ t('nda.register.title') }}</h1>
        <RouterLink :to="{ name: ROUTE_NAMES.ADMIN_SETTINGS_NDA }" class="nda-admin__note">
          {{ t('nda.register.back') }}
        </RouterLink>
      </div>
      <div class="nda-admin__actions">
        <BaseButton
          variant="secondary"
          icon="bell"
          :loading="busy === 'remind'"
          :disabled="!version || summary.pending === 0 || Boolean(busy)"
          data-testid="nda-remind"
          @click="register.remind"
        >
          {{ t('nda.register.remind') }}
        </BaseButton>
        <BaseButton
          variant="secondary"
          icon="download"
          :loading="busy === 'export'"
          :disabled="!version || Boolean(busy)"
          @click="register.exportCsv"
        >
          {{ t('nda.register.export') }}
        </BaseButton>
      </div>
    </header>

    <StatsCards :stats="stats" :loading="status === 'loading'" :skeleton-count="3" />

    <div class="nda-scope__chips" role="radiogroup" :aria-label="t('nda.register.filterLabel')">
      <button
        v-for="option in NDA_FILTERS"
        :key="option"
        type="button"
        role="radio"
        class="nda-scope__chip"
        :aria-checked="filter === option ? 'true' : 'false'"
        @click="filter = option"
      >
        {{ t(`nda.register.filters.${option}`) }}
      </button>
    </div>

    <NdaRegisterTable
      :rows="rows"
      :status="status"
      :error-message="errorMessage"
      :current-version="version?.version ?? ''"
      @retry="register.load"
    />
    <Pagination v-if="lastPage > 1" v-model:page="page" :last-page="lastPage" />
  </section>
</template>
