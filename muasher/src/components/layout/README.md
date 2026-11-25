# components/layout

The layouts, wired through `meta.layout` (`public` | `landing` | `auth` | `app`) in `src/App.vue`. `LandingLayout` serves the home page only (the CMS-driven landing: its own header, loader, footer and assistant, styles in `styles/landing/`). An `app` route opens in
`PortalLayout` for entity-only accounts and in `AppLayout` for every other account (`utils/portalAudience.js`), so every
route keeps its path for both audiences:

- `PortalLayout` — entity portal (Figma page 14): skip link, `portal/PortalTopBar` (logo, رحلتي · تقييماتي ·
  شهاداتي · مدفوعاتي · صندوق الرسائل with the unread-reply badge, bell, account menu overlay; logo, bell and avatar → `/more`
  below `lg`), `portal/ProfileCompletionBanner` on hub screens (only while the entity profile is incomplete), the `<main id="portal-main">` slot, `portal/PortalFooter` and
  `portal/MobileTabBar` below `lg`. Task screens (`PORTAL_TASK_ROUTES` in `constants/portal.js`) hide the banner and the
  tab bar; the thread route hides them on mobile only.

- `AppLayout` — back-office shell on the TTS Panel Design System "Modern layout": skip link, a floating
  `TopNavbar` across the page (secondary brand colour), then a body row with the white `Sidebar` at the inline start
  and the `<main id="app-main">` slot. Loads the shared branding theme (`stores/branding.js`) once. For `app` routes
  `App.vue` forces `data-theme="light"` (the back office has no dark mode), sets `data-shell="tts"` and injects the
  saved brand scheme as `--brand-*` custom properties (`composables/useBrandSchemeStyle.js`) through a
  constructable stylesheet (`composables/useAdoptedStyleSheet.js`), since the production CSP refuses inline `<style>`.
- Phones (below `md`, 768 px): the back office behaves as an app. `AppTabBar` (the portal tab bar's look,
  `.mobile-tab-bar--app`) shows the dashboard, up to three destinations from the role's
  `ROLE_TAB_PRIORITY` list in `constants/navigation.js` (only items the account may open, through the
  sidebar's own `filterNavByAccess`; Settings is a candidate too) and «المزيد», which opens `/menu`
  (`views/app/AppMore.vue`: the remaining destinations grouped as in the sidebar, the account entries,
  the language switch, the platform preview and sign out). Entity-only accounts keep the portal's
  `/more`; `/menu` sends them there. A tab stays lit on the screens below its path. The `Sidebar` stays
  mounted (its pending counts feed the tabs) but is hidden, and `TopNavbar` swaps its toggle for
  `AppTopBarPhone`: the logo on a tab's own screen, else a back button (the in-app previous screen, or
  the owning tab after a deep link; it never leaves the platform) and the screen title (visual only;
  the page's `h1` names it). Bottom layers (the update banner, sticky step bars) add
  `--app-tab-bar-offset`, which the tab bar sets while it shows (`_mobile-tab-bar.scss`).
- Phones also get `PhoneNavDrawer` at the start of the top bar (staff: `AppTopBarPhone`; portal:
  `PortalTopBar`): a menu button that opens a start-side drawer (`BaseModal layout="drawer-start"`)
  holding the shell's own side menu (`<Sidebar variant="drawer">`, or `<PortalSideNav variant="drawer">`: no
  logo or preview card, never collapsed, every group open, the panel in the menu's gradient), so its
  sections and access are exactly the desktop ones; it closes on Esc, the backdrop, × and navigation.
  Page header actions use `ui/PageActions.vue`: a row from md up, one «الإجراءات» button and a
  bottom sheet on a phone when there are two or more (a single action stays a button).
- `Sidebar` — white panel, items from `src/constants/navigation.js` filtered by role and grouped by module
  (`NAV_GROUPS`), one caption per group, dividers between groups. The active item (route name or a child route name)
  gets `aria-current="page"` and the primary brand fill. Hidden below `md` (the phone tab bar takes over); collapsible from `md` up through
  the ui store. It opens with `SidebarIdentityCard`.
- `SidebarIdentityCard` — the platform card at the top of `Sidebar` and of the portal `PortalSideNav` (TTS model):
  the site logo (made for a light background) linking to the dashboard, then «معاينة المنصة», which opens the public
  home page in a new tab. Collapsed, it shows the small logo and an icon-only link that keeps its accessible name.
- `TopNavbar` (`.navbar.navbar--app`) — no logo (the logo sits in the sidebar card), sidebar toggle, global search,
  LocaleSwitcher, settings shortcut, NotificationBell, avatar user menu (My account, entity profile, sign out).
  No theme toggle.
- `PublicLayout` / `PublicNavbar` / `AppFooter` — public pages (base `.navbar` styles).
- `AuthLayout` — the landing look (`.lp-auth`, landing tokens): `<main>` first with a top row (back-to-home link,
  language button, landing theme switch) and the form card, then the brand panel `<aside>` (deep band, arrow
  pattern, logo on dark and the floating trophy). The aside sits at the inline start through CSS `order`; below `lg`
  it is a compact logo band; from `lg` its inner column is sticky. Also hosts the maintenance page.
  `App.vue` hands the routed page to the layout through the `router-view` slot, so the layout wraps it in a
  `<Transition mode="out-in">` driven by `useAuthPageTransition`: sign-in, register, forgot password, the
  verification code (`/auth/otp`) and the authenticator code (`/auth/mfa`) slide along the reading direction
  (forward from sign-in, back to it), the card viewport eases its height (`useAuthViewport`), and reduced motion
  swaps pages with no motion. When focus was in the card it moves to the field the new page marks with
  `data-auth-autofocus` (the first code box, or the password when the identifier is known), else to the new `h1`.
  In `SignInMethodTabs` a highlighted pill slides under the tabs like a switch knob, and the password and code
  forms swipe past each other in step with it (`useAuthPanelTransition`; the leaving form lifts out of the flow
  and is `inert`), keeping focus on the chosen tab. On the code step the boxes arrive in sequence
  (`OtpInput` `entrance`), a wrong code shakes the row once, a ring beside the resend link drains with the
  cooldown (`OtpResendRing`), and an accepted code turns the boxes green with a tick for `verifiedHoldMs` before
  the next screen opens (none of it under reduced motion). The slide and focus rules live in `composables/auth/authSlide.js` and `authFocus.js`. The layout
  warms the chunks of those pages when idle and, through `useRouteIntentPrefetch`, the register data when the
  pointer, focus or a touch lands on a link to it (`LandingLayout` does the same).
- `AuthBrandPanel` — the brand `<aside>`. On the split layout it adds the landing hero dot field: it reuses
  `useHeroDots` (the lens follows the cursor, the pattern drifts, the logo stays shielded); below `lg` it draws no
  canvas.

Page width: `<main>` (`.app-layout__content`, `.portal-layout__main`) is the one content container. It
fills the column beside the sidebar or side nav at every width, so collapsing either widens every page the same
way; the shell alone sets the gutters (`--tts-page-gutter`, `--tts-body-gap`). A page root or band never sets a
`max-inline-size`, `margin-inline: auto` or outer `padding-inline`; full-bleed bands pad their own content with
`portal-gutter`. Only the public layout centres content at `--size-container`
(`src/styles/__tests__/pageWidth.spec.js` checks this).

Styling: `_sidebar.scss` (hidden below `md`), `_identity-card.scss`, `_navbar.scss`,
`_app-layout.scss`, `_portal-layout.scss`, `_portal-top-bar.scss`, `_profile-banner.scss`, `_mobile-tab-bar.scss`, `_public-layout.scss`, `_auth-layout.scss`, `_footer.scss`. No style block or
inline style in any `.vue`.
The TTS look of the shared components inside the back office (buttons, fields, tables, menus, tabs, pagination,
modals, badges, stats cards) lives in `src/styles/tts/` and applies only under `:root[data-shell='tts']`
(`mixins/_tts-shell.scss`); tokens in `tokens/_tts.scss`, theme variables in `themes/_tts-shell.scss`.
