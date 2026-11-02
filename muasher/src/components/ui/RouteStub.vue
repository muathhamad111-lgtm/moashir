<script setup>
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

const { t } = useI18n()
const route = useRoute()
</script>

<template>
  <section class="route-stub">
    <h1 class="route-stub__title">
      {{ route.meta.titleKey ? t(route.meta.titleKey) : t('app.name') }}
    </h1>
    <p class="route-stub__note">{{ t('stub.note') }}</p>
  </section>
</template>
