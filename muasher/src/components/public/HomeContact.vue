<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { SOCIAL_PLATFORMS } from '@/constants/socialPlatforms'

const props = defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
  siteSettings: { type: Object, default: null },
})

const { t } = useI18n()

const contact = computed(() => props.siteSettings?.contact ?? null)

const social = computed(() =>
  (props.siteSettings?.social ?? [])
    .map((link) => ({ ...link, meta: SOCIAL_PLATFORMS[link.platform] }))
    .filter((link) => link.meta && link.url),
)

const email = computed(() => contact.value?.email ?? '')
const phone = computed(() => contact.value?.phone ?? '')
const address = computed(() =>
  contact.value?.address ? props.localized(contact.value.address) : '',
)
</script>

<template>
  <div class="home-section home-contact" aria-labelledby="home-contact-title">
    <div class="home-section__container home-contact__grid">
      <header class="home-section__head home-contact__intro">
        <span class="home-contact__icon" aria-hidden="true">
          <i :class="section.icon || 'fa-solid fa-envelope'"></i>
        </span>
        <h2 id="home-contact-title" class="home-section__title">
          {{ localized(section.title) }}
        </h2>
        <p v-if="section.subtitle" class="home-section__subtitle">
          {{ localized(section.subtitle) }}
        </p>
        <p v-if="section.description" class="home-section__description">
          {{ localized(section.description) }}
        </p>
      </header>

      <div class="home-contact__details">
        <ul class="home-contact__list">
          <li v-if="email" class="home-contact__item">
            <span class="home-contact__item-icon" aria-hidden="true">
              <i class="fa-solid fa-envelope"></i>
            </span>
            <span class="home-contact__item-body">
              <span class="home-contact__item-label">{{ t('public.home.contact.email') }}</span>
              <a class="home-contact__item-value" :href="`mailto:${email}`">{{ email }}</a>
            </span>
          </li>
          <li v-if="phone" class="home-contact__item">
            <span class="home-contact__item-icon" aria-hidden="true">
              <i class="fa-solid fa-phone"></i>
            </span>
            <span class="home-contact__item-body">
              <span class="home-contact__item-label">{{ t('public.home.contact.phone') }}</span>
              <a class="home-contact__item-value" :href="`tel:${phone.replace(/\s+/g, '')}`" dir="ltr">
                {{ phone }}
              </a>
            </span>
          </li>
          <li v-if="address" class="home-contact__item">
            <span class="home-contact__item-icon" aria-hidden="true">
              <i class="fa-solid fa-location-dot"></i>
            </span>
            <span class="home-contact__item-body">
              <span class="home-contact__item-label">{{ t('public.home.contact.address') }}</span>
              <span class="home-contact__item-value">{{ address }}</span>
            </span>
          </li>
        </ul>

        <ul v-if="social.length" class="home-contact__social" :aria-label="t('public.home.contact.social')">
          <li v-for="link in social" :key="link.platform">
            <a
              class="home-contact__social-link"
              :href="link.url"
              target="_blank"
              rel="noopener"
              :aria-label="t(link.meta.labelKey)"
            >
              <i :class="link.meta.icon" aria-hidden="true"></i>
            </a>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
