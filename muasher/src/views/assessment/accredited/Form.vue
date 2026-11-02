<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@unhead/vue'
import AccreditedFormWorkspace from '@/components/accredited/AccreditedFormWorkspace.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const assessmentId = computed(() => Number(route.params.id))

useHead({ title: () => t('accredited.form.title') })

function onSubmitted() {
  router.push({ name: ROUTE_NAMES.ASSESSMENT_ACCREDITED })
}
</script>

<template>
  <section class="accredited-form">
    <header class="accredited-form__header">
      <p class="accredited-form__eyebrow">{{ t('accredited.intro.eyebrow') }}</p>
      <h1 class="accredited-form__title">{{ t('accredited.form.title') }}</h1>
      <p class="accredited-form__subtitle">{{ t('accredited.form.subtitle') }}</p>
    </header>
    <div class="accredited-form__note" role="note">
      <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
      <p>{{ t('accredited.form.note') }}</p>
    </div>
    <AccreditedFormWorkspace :key="assessmentId" :assessment-id="assessmentId" @submitted="onSubmitted" />
  </section>
</template>
