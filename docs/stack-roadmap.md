# Stack roadmap

Laravel is intentionally frontend-agnostic, so the space of valid stacks is enormous. This
page tracks which combinations the installer supports today — via the CLI, the web wizard,
and any future desktop shell (all three drive the same `laravel new` flags) — and which are
planned. Adding a preset is usually one entry in
[`src/Concerns/InstallsUiPresets.php`](../src/Concerns/InstallsUiPresets.php) plus a wizard
card, so this list grows cheaply.

## Supported today

| Category | Options | How |
| --- | --- | --- |
| Project types | Web app, API-only (Sanctum via `install:api`), Filament dashboard, Package | `--type=`, wizard step |
| Official starter kits | React, Svelte, Vue, Livewire × Laravel/WorkOS auth × teams | starter kit flags |
| Blank Inertia kits | React, Svelte, Vue, Livewire (no auth) | `--react` etc. + `--no-authentication` |
| Blade CSS frameworks | Tailwind (default), Bootstrap 5, Bulma, UIkit, Pico CSS | `--ui=` |
| Admin UI kits | CoreUI 5, AdminLTE 4, Laravel AdminLTE, Filament (via dashboard type) | `--ui=`, `--type=dashboard` |
| JS enhancements | Alpine.js, HTMX, jQuery, Stimulus | `--js=` |
| SPA frontends | Angular, Next.js, Nuxt, SvelteKit, Astro (in `frontend/`) | `--spa=` |
| Theming | Light/dark helper (`window.toggleTheme`, system preference + localStorage) | `--theme` |
| Community kits | Any Packagist package or git URL | `--using=` |
| Custom location | Any existing directory | wizard location field / absolute path |

The classic combos from the taxonomy map directly: **Modern Laravel** = `--ui` default +
`--js=alpine --theme`; **Classic Laravel** = `--ui=bootstrap --js=jquery`; **HTMX stack** =
`--js=htmx`; **TALL** = the Livewire starter kit; **admin panel** = `--type=dashboard`;
**API + mobile backend** = `--type=api`.

## Planned

- **CSS frameworks**: Foundation, Semantic/Fomantic UI, Materialize, PureCSS, Milligram,
  Spectre.css, Tachyons, UnoCSS — same Vite-swap mechanism, pending demand.
- **Livewire ecosystem**: Flux UI, WireUI, Mary UI, PowerGrid, Volt presets on top of the
  Livewire kit.
- **Hotwire**: Turbo + Stimulus as a combined preset (`turbo-laravel`).
- **SPA ecosystem picks**: Pinia/Vue Router bundles for Vue, TanStack Query/Zustand for
  React, component libraries (Vuetify, PrimeVue, MUI, Chakra, Mantine, shadcn/ui) as
  scaffold-time options inside `frontend/`.
- **More SPA frameworks**: Remix/React Router, SolidStart, Qwik — pending stable
  non-interactive scaffolding CLIs.
- **More admin panels**: Backpack, Orchid, MoonShine (Nova is commercial).
- **Auth providers**: Socialite pre-configuration, JWT, Passport (`install:api --passport`),
  Clerk/Auth0 guides.
- **Mobile/desktop companions**: Flutter, React Native, Ionic, Electron, Tauri, Wails
  scaffolds next to the API backend — the `--spa` mechanism generalizes to these.
- **Use-case recipes**: one-flag bundles matching the recommendation table (blog/CMS,
  SaaS, ERP/CRM, e-commerce, real-time with Reverb).

## Design rule

Every capability must be expressible as `laravel new` flags first. The wizard, and any
future desktop app, are frontends over that contract — that is what keeps terminal,
browser, and desktop setup equivalent.
