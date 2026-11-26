<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import { useSiteSettingsEditor } from '@/composables/useSiteSettingsEditor'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  headingId: { type: String, required: true },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  fields: { type: Array, required: true },
})

const { t } = useI18n()
const toast = useToast()
const { status, draft, saving, load, save, errorOf } = useSiteSettingsEditor({
  fields: props.fields,
})

async function onSubmit() {
  try {
    await save()
    toast.success(t('settings.homepage.siteSettings.toasts.saved'))
  } catch (error) {
    toast.error(error?.normalized?.message || t('settings.homepage.siteSettings.toasts.saveFailed'))
  }
}
</script>

<template>
  <section class="site-settings" :aria-labelledby="headingId">
    <header class="site-settings__header">
      <h2 :id="headingId" class="site-settings__title">{{ title }}</h2>
      <p v-if="description" class="site-settings__desc">{{ description }}</p>
    </header>

    <div v-if="status === 'loading'" class="site-settings__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="4" />
    </div>

    <ErrorState
      v-else-if="status === 'error'"
      :message="t('settings.homepage.siteSettings.error')"
      @retry="load"
    />

    <form
      v-else
      class="site-settings__form"
      :aria-busy="saving || undefined"
      novalidate
      @submit.prevent="onSubmit"
    >
      <slot v-bind="{ draft, errorOf, saving }" />
      <div class="site-settings__footer">
        <BaseButton type="submit" variant="primary" :loading="saving">
          {{ t('settings.homepage.siteSettings.save') }}
        </BaseButton>
      </div>
    </form>
  </section>
</template>
