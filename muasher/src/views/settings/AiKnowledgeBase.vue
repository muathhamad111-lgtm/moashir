<script setup>
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import { useKnowledgeBase } from '@/composables/useKnowledgeBase'

const { t } = useI18n()
const { files, status, retraining, load, upload, toggleApproved, remove, retrain } = useKnowledgeBase()
</script>

<template>
  <SettingsSection :title="t('settings.ai.kbTitle')" :description="t('integrations.knowledgeBase.description')">
    <section class="settings-form__group" aria-labelledby="kb-title">
      <div class="settings-form__group-head">
        <h2 id="kb-title" class="settings-form__legend">{{ t('integrations.knowledgeBase.filesTitle') }}</h2>
        <div class="settings-form__inline">
          <BaseButton variant="secondary" size="sm" icon="fa-solid fa-cloud-arrow-up" @click="upload">{{ t('settings.ai.upload') }}</BaseButton>
          <BaseButton variant="ghost" size="sm" icon="fa-solid fa-arrows-rotate" :loading="retraining" @click="retrain">{{ t('settings.ai.retrain') }}</BaseButton>
        </div>
      </div>
      <p class="settings-form__note" role="note">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        {{ t('settings.ai.kbNote') }}
      </p>
      <div v-if="status === 'loading'" class="settings-form__kb-loading" aria-busy="true">
        <Skeleton variant="block" :count="3" />
      </div>
      <ErrorState v-else-if="status === 'error'" @retry="load" />
      <EmptyState v-else-if="status === 'empty'" :title="t('settings.ai.kbEmptyTitle')" :message="t('settings.ai.kbEmptyMessage')">
        <template #action>
          <BaseButton variant="primary" icon="fa-solid fa-cloud-arrow-up" @click="upload">{{ t('settings.ai.upload') }}</BaseButton>
        </template>
      </EmptyState>
      <ul v-else class="settings-form__kb-list">
        <li v-for="file in files" :key="file.id" class="settings-form__kb-item">
          <i class="fa-solid fa-file-lines settings-form__kb-icon" aria-hidden="true"></i>
          <div class="settings-form__kb-meta">
            <span class="settings-form__kb-name">{{ file.name }}</span>
            <span class="settings-form__muted">{{ t('settings.ai.fileSize', { size: file.sizeKb }) }}</span>
          </div>
          <StatusBadge :status="file.status" />
          <Badge :variant="file.approved ? 'success' : 'muted'">{{ file.approved ? t('settings.ai.approved') : t('settings.ai.notApproved') }}</Badge>
          <div class="settings-form__kb-actions">
            <BaseButton
              variant="ghost"
              size="sm"
              :icon="file.approved ? 'fa-solid fa-xmark' : 'fa-solid fa-check'"
              :disabled="file.status !== 'processed'"
              @click="toggleApproved(file)"
            >
              {{ file.approved ? t('settings.ai.unapprove') : t('settings.ai.approve') }}
            </BaseButton>
            <BaseButton variant="ghost" size="sm" icon-only icon="fa-solid fa-trash-can" :aria-label="t('settings.ai.removeFile', { name: file.name })" @click="remove(file)" />
          </div>
        </li>
      </ul>
    </section>
  </SettingsSection>
</template>
