<script setup>
import { useI18n } from 'vue-i18n'
import { useLocale } from '@/composables/useLocale'
import { ROUTE_NAMES } from '@/constants/routeNames'
import LegalLink from '@/components/legal/LegalLink.vue'

const { t } = useI18n()
const { toggleLocale } = useLocale()
const year = new Date().getFullYear()
</script>

<template>
  <footer class="portal-footer">
    <div class="portal-footer__inner">
      <p class="portal-footer__copy">
        {{ t('portal.footer.copyright', { year: String(year) }) }}
      </p>
      <ul class="portal-footer__links">
        <li>
          <router-link class="portal-footer__link" :to="{ name: ROUTE_NAMES.PORTAL_HELP }">
            {{ t('portal.footer.help') }}
          </router-link>
        </li>
        <li>
          <LegalLink kind="privacy" link-class="portal-footer__link" />
        </li>
        <li>
          <button type="button" class="portal-footer__link" @click="toggleLocale">
            {{ t('locale.switchLabel') }}
          </button>
        </li>
      </ul>
    </div>
  </footer>
</template>
