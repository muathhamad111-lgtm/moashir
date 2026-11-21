import { readCookie } from '../../contract/cookies'

const SCENARIO_COOKIE = 'moashir_mock_scenario'

export const MENU_DROPDOWN_SCENARIO = 'menu-dropdown'

const SCENARIO_GROUP = Object.freeze({
  label: { ar: 'عن المنصة', en: 'About the platform' },
  url: null,
  isVisible: true,
  children: [
    { label: { ar: 'نموذج التميّز', en: 'Excellence model' }, url: '/#model', isVisible: true },
    { label: { ar: 'مؤشرات الأداء', en: 'Performance indicators' }, url: '/kpis', isVisible: true },
    { label: { ar: 'سياسة الخصوصية', en: 'Privacy policy' }, url: '/privacy', isVisible: false },
  ],
})

function isMenuScenario() {
  return typeof document !== 'undefined' && readCookie(SCENARIO_COOKIE) === MENU_DROPDOWN_SCENARIO
}

export function scenarioHeaderLinks(links) {
  return isMenuScenario() ? [...(links ?? []).slice(0, 3), SCENARIO_GROUP] : links
}
