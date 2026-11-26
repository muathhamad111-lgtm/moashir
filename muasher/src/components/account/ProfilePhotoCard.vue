<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useProfilePhoto } from '@/composables/useProfilePhoto'
import { ACCOUNT_AVATAR_RULES } from '@/constants/accountAvatar'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AccountAvatar from '@/components/account/AccountAvatar.vue'

const { t } = useI18n()
const { photo, busy, error, upload, remove } = useProfilePhoto()
const input = ref(null)

function choose() {
  input.value?.click()
}

function onChange(event) {
  const [file] = event.target.files ?? []
  event.target.value = ''
  if (file) upload(file)
}
</script>

<template>
  <Card :title="t('account.avatar.title')" class="profile-photo" data-testid="profile-photo">
    <div class="profile-photo__body">
      <span class="profile-photo__preview">
        <AccountAvatar />
      </span>
      <div class="profile-photo__details">
        <p class="profile-photo__hint">{{ t('account.avatar.hint') }}</p>
        <p class="profile-photo__rules">
          {{
            t('account.avatar.rules', {
              min: ACCOUNT_AVATAR_RULES.minSide,
              max: ACCOUNT_AVATAR_RULES.maxSide,
            })
          }}
        </p>
        <p v-if="error" id="profile-photo-error" class="profile-photo__error" role="alert">
          {{ error }}
        </p>
        <div class="profile-photo__actions">
          <BaseButton variant="primary" icon="upload" size="sm" :loading="busy" @click="choose">
            {{ photo ? t('account.avatar.replace') : t('account.avatar.upload') }}
          </BaseButton>
          <BaseButton
            v-if="photo"
            variant="ghost"
            icon="trash"
            size="sm"
            :disabled="busy"
            data-testid="profile-photo-remove"
            @click="remove"
          >
            {{ t('account.avatar.remove') }}
          </BaseButton>
        </div>
      </div>
    </div>
    <input
      ref="input"
      class="profile-photo__input"
      type="file"
      :accept="ACCOUNT_AVATAR_RULES.accept"
      :aria-label="t('account.avatar.choose')"
      :aria-describedby="error ? 'profile-photo-error' : undefined"
      tabindex="-1"
      data-testid="profile-photo-input"
      @change="onChange"
    />
  </Card>
</template>
