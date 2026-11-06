<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useContactPrivacy } from '@/composables/useContactPrivacy'
import { formatNumber } from '@/utils/formatNumber'

const { t, n } = useI18n()
const { form, errors, busy, exported, erased, exportMessages, eraseMessages, downloadExport } =
  useContactPrivacy()

const exportedCount = computed(() => exported.value?.messages?.length ?? 0)
</script>

<template>
  <Card class="contact-privacy" aria-labelledby="contact-privacy-title">
    <template #header>
      <h2 id="contact-privacy-title" class="card__title-text">
        {{ t('entities.privacy.contact.title') }}
      </h2>
    </template>
    <p class="contact-privacy__intro">{{ t('entities.privacy.contact.intro') }}</p>
    <form class="contact-privacy__form" novalidate @submit.prevent="exportMessages">
      <BaseInput
        v-model="form.email"
        name="contactPrivacyEmail"
        type="email"
        :label="t('entities.privacy.contact.email')"
        :error="errors.email"
        input-dir="ltr"
        autocomplete="off"
        required
      />
      <BaseTextarea
        v-model="form.reason"
        name="contactPrivacyReason"
        :label="t('entities.privacy.contact.reason')"
        :hint="t('entities.privacy.contact.reasonHint')"
        :error="errors.reason"
        :rows="3"
        required
      />
      <div class="contact-privacy__actions">
        <BaseButton type="submit" variant="primary" icon="download" :loading="busy === 'export'">
          {{ t('entities.privacy.contact.export') }}
        </BaseButton>
        <BaseButton
          variant="danger"
          icon="trash"
          :loading="busy === 'erase'"
          @click="eraseMessages"
        >
          {{ t('entities.privacy.contact.erase') }}
        </BaseButton>
      </div>
    </form>
    <div v-if="exported" class="contact-privacy__result" role="status">
      <p>
        {{ t('entities.privacy.contact.exportedCount', { count: formatNumber(n, exportedCount) }) }}
      </p>
      <ul v-if="exportedCount" class="contact-privacy__references">
        <li v-for="item in exported.messages" :key="item.reference">
          <bdi dir="ltr">{{ item.reference }}</bdi>
        </li>
      </ul>
      <BaseButton
        v-if="exportedCount"
        variant="secondary"
        size="sm"
        icon="download"
        @click="downloadExport"
      >
        {{ t('entities.privacy.contact.downloadFile') }}
      </BaseButton>
    </div>
    <p v-if="erased" class="contact-privacy__result" role="status">
      {{
        t('entities.privacy.contact.erasedCount', {
          messages: formatNumber(n, erased.erasedMessages ?? 0),
          entries: formatNumber(n, erased.erasedEntries ?? 0),
        })
      }}
    </p>
  </Card>
</template>
