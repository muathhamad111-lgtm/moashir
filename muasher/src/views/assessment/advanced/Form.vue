<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@unhead/vue'
import AccreditedFormWorkspace from '@/components/accredited/AccreditedFormWorkspace.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useDraftDeletion } from '@/composables/useDraftDeletion'
import { ADVANCED_STATUSES } from '@/constants/advanced'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const assessmentId = computed(() => Number(route.params.id))
const { deleting, deleteDraft } = useDraftDeletion()

useHead({ title: () => t('advanced.form.title') })

function backToIntro() {
  router.push({ name: ROUTE_NAMES.ASSESSMENT_ADVANCED })
}

async function onDelete(assessment) {
  if (await deleteDraft(assessment)) backToIntro()
}
</script>

<template>
  <section class="accredited-form">
    <header class="accredited-form__header">
      <p class="accredited-form__eyebrow">{{ t('advanced.eyebrow') }}</p>
      <h1 class="accredited-form__title">{{ t('advanced.form.title') }}</h1>
      <p class="accredited-form__subtitle">{{ t('advanced.form.subtitle') }}</p>
    </header>
    <div class="accredited-form__note" role="note">
      <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
      <p>{{ t('advanced.form.note') }}</p>
    </div>
    <AccreditedFormWorkspace
      :key="assessmentId"
      :assessment-id="assessmentId"
      @submitted="backToIntro"
    >
      <template #extra-actions="{ assessment }">
        <BaseButton
          v-if="assessment.status === ADVANCED_STATUSES.DRAFT"
          variant="danger"
          icon="fa-solid fa-trash-can"
          :loading="deleting"
          @click="onDelete(assessment)"
        >
          {{ t('advanced.draft.delete') }}
        </BaseButton>
      </template>
    </AccreditedFormWorkspace>
  </section>
</template>
