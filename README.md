<p align="center">
  <img src="https://img.shields.io/badge/EveryAlt-1.1.0-7c3aed?style=for-the-badge&labelColor=5b21b6" alt="EveryAlt 1.1.0" />
  <img src="https://img.shields.io/badge/WordPress-5.5%2B-21759b?style=flat-square&logo=wordpress" alt="WordPress" />
  <img src="https://img.shields.io/badge/PHP-7.0%2B-777BB4?style=flat-square&logo=php" alt="PHP" />
  <img src="https://img.shields.io/badge/license-GPLv2-green?style=flat-square" alt="License" />
</p>

# 🖼️ EveryAlt — Alt text for every image. **Zero limits. Totally free.**

<p align="center">
  <strong>🆓 100% free</strong> · <strong>♾️ No limits from us</strong> · <strong>🔑 Your AI key: OpenAI, Gemini, or DeepInfra</strong>
</p>

> **EveryAlt 1.0 is 100% free.** Bring your own API key from **OpenAI**, **Google Gemini**, or **DeepInfra**, and generate as much alt text as you want. We don’t meter you, cap you, or bill you. **Ever.**

---

## ✨ What is this?

**EveryAlt** is a WordPress plugin that uses AI to write **alternative text** for your images. One click (or automatic on upload), and you get clear, descriptive alt text — better accessibility, happier search engines, and ADA-friendly media.

No more blank alt fields. No more guessing. **You upload. EveryAlt writes.**

---

## 🎉 Why 1.0 is different

| Before | **EveryAlt 1.0** |
|--------|------------------|
| Limits, tiers, or paywalls | **Unlimited** — your key, your usage |
| Extra service to sign up for | **Your choice of provider** — one key, no EveryAlt account |
| Opaque pricing | **Transparent** — you pay your AI provider only |

We don’t charge. We don’t sit in the middle. You use **your** own key. EveryAlt stays **free**, and you have **no limits** from us.

---

## 🚀 What you can do

- **Auto-generate on upload** — New images get alt text as soon as they hit the Media Library.
- **AI image titles** — Generate descriptive WordPress image titles too, not just alt text — automatically on upload, in bulk, or one at a time.
- **Bulk generate in the background** — Queue hundreds or thousands of images and close the tab; EveryAlt keeps working, retries failures, and uploads never wait for the AI.
- **Review & edit** — See everything that already has alt text or a title; edit or regenerate anytime.
- **In the block editor** — Generate or regenerate alt text right from the Image block.
- **On the media screen** — Button next to the alt field for single images.
- **Logs & export** — See what ran, what cost what, and export as CSV.
- **Fixes older posts too (optional)** — Turn it on and images already in posts pick up their new alt text automatically.
- **Decorative images** — Purely decorative images (dividers, spacers, patterns) get empty alt text so screen readers skip them, as WCAG recommends.
- **Spending limits** — See estimated spend per month and per model, and set a monthly cap.
- **Your language** — Alt text and titles are written in your site's language (Polylang and WPML supported).

Works on localhost, behind HTTP auth, and with your existing workflow.

---

## 📦 Install

**Download the latest release:** [Releases](https://github.com/EveryAlt/everyalt-wordpress-plugin/releases/) — grab the `.zip` for the version you want.

1. **Upload** the plugin to `wp-content/plugins/everyalt` (or install via WordPress admin).
2. **Activate** the plugin (Plugins → EveryAlt → Activate).
3. **Open Media → EveryAlt → Settings**, pick an **AI model**, and paste the API key for that provider (see [Models & keys](#-models--keys)).
4. Turn on **Auto-generate on upload** if you want, or use **Bulk** / **Review** to handle existing images.

That’s it. No account with us. No caps. No “upgrade to unlock more.”

---

## 🤖 Models & keys

Choose a model in **Settings**. Every model reads the image itself and writes the alt text (and titles). You only need a key for the provider you pick, and you can store keys for several providers and switch any time.

| Model | Provider | Input / 1M tokens | Output / 1M tokens |
|-------|----------|------------------:|-------------------:|
| **GPT-5.4 nano** (default) | OpenAI | $0.20 | $1.25 |
| **Gemini 3.1 Flash-Lite** | Google Gemini | $0.25 | $1.50 |
| **DeepSeek V4.1 Flash** | DeepInfra | $0.20 | $0.60 |
| **GLM-5.3-Flash** | DeepInfra | $0.15 | $0.50 |

Prices are each provider’s published regular rates as of September 2026 and can change: [OpenAI pricing](https://openai.com/api/pricing/) · [Gemini pricing](https://ai.google.dev/gemini-api/docs/pricing) · [DeepInfra pricing](https://deepinfra.com/pricing). The actual cost of every image is recorded on the **Logs** tab.

> **Upgrading from 1.0.x?** Earlier versions used `gpt-5-nano`, which OpenAI is retiring. Existing installs switch to **GPT-5.4 nano** automatically. Your OpenAI key keeps working.

### Getting a key

**OpenAI**
1. Sign in at [platform.openai.com/api-keys](https://platform.openai.com/api-keys).
2. Click **Create new secret key**, name it (e.g. “EveryAlt”), and copy it. It is only shown once.
3. Add a payment method or prepaid credits under billing; generation fails until the account has credit.

OpenAI does not train on API data by default ([details](https://openai.com/enterprise-privacy/)).

**Google Gemini**
1. Sign in to Google AI Studio at [aistudio.google.com/apikey](https://aistudio.google.com/apikey).
2. Click **Create API key**, pick or create a Google Cloud project, and copy the key.
3. Optional: enable billing on the project to use the paid tier.

Gemini has a free tier, but Google may use free-tier content to improve its products; paid-tier content is not used that way ([Gemini API terms](https://ai.google.dev/gemini-api/terms)).

**DeepInfra** (one key covers both DeepSeek V4.1 Flash and GLM-5.3-Flash)
1. Sign in or sign up at [deepinfra.com/dash/api_keys](https://deepinfra.com/dash/api_keys).
2. Click **New API key**, name it, and copy it.
3. Add a payment method or credits in your DeepInfra billing settings.

🔒 **Your images stay private with DeepInfra.** DeepInfra runs these models on its own infrastructure in data centers in the **US and Canada**, with **zero data retention**: images and generated text are processed in memory and not stored, request content is not logged, and your data is never used for training. See [DeepInfra data privacy](https://docs.deepinfra.com/account/data-privacy), the [privacy policy](https://deepinfra.com/privacy), and the [trust center](https://trust.deepinfra.com/) (SOC 2 and ISO 27001).

### Your key, your usage

- The image is sent to your chosen provider as base64, so it works on localhost and behind HTTP auth.
- API keys are **stored encrypted** in your WordPress database.
- **You** are billed by your provider for usage; EveryAlt does not charge you.

### For developers

- `everyalt_model` filters the model ID sent to the provider: `( string $model_id, string $model_slug )`. This replaces `everyalt_openai_model`.
- `everyalt_output_locale` changes the language text is written in: `( string $locale, int $attachment_id )`.
- `everyalt_vision_prompt` / `everyalt_title_prompt` now also receive the attachment ID, and the prompt they receive already includes the language instruction.
- Queue REST routes (admins only): `GET/POST/DELETE everyalt-api/v1/queue` (status / add `{type, media_ids | all}` / clear) and `POST everyalt-api/v1/queue/process`.
- `bulk_generate_alt` accepts `describe: true` to skip decorative detection, and returns `decorative`.
- `everyalt_input_token_price_per_million` / `everyalt_output_token_price_per_million` override the prices used for cost estimates: `( float $price, string $model_slug )`.

---

## 📋 Requirements

- WordPress 5.5+
- PHP 7.0+
- An API key from [OpenAI](https://platform.openai.com/api-keys), [Google Gemini](https://aistudio.google.com/apikey), or [DeepInfra](https://deepinfra.com/dash/api_keys)

---

## 🌐 Languages

EveryAlt is translation-ready. These languages are included:

| Language | Locale |
|----------|--------|
| Spanish (Español) | es_ES |
| Italian (Italiano) | it_IT |
| Japanese (日本語) | ja |
| French (Français) | fr_FR |
| Brazilian Portuguese (Português do Brasil) | pt_BR |
| German (Deutsch) | de_DE |
| Dutch (Nederlands) | nl_NL |

The plugin uses the `everyalt` text domain and ships with a `.pot` in `languages/` so you can add or update translations.

---

## 📝 Changelog

### 1.1.0
- **Accessibility (WCAG 2.1 AA).** A full review of every screen, with all failures fixed:
  - Review tabs: alt text and title fields now have labels; Save, Regenerate, and Edit identify which image they act on.
  - Results (key validation, queue progress, saves, the Media screen button) are announced to screen readers via WordPress's `wp.a11y.speak`, once per batch rather than once per image.
  - Keyboard focus stays on buttons while they work, instead of jumping to the top of the page.
  - Success text and the "Key saved" badge now meet 4.5:1 contrast.
  - Each tab has its own page title; the tab navigation is a `nav` landmark with the current tab marked.
  - The Logs spending bar has an accessible name and value; scrollable log boxes are keyboard-reachable; links that open a new tab say so.
- **Changed** **Existing posts** (filling missing alt text in posts) is now **off by default**, so it's only applied when a site owner turns it on.
- **Improved** the default alt text prompt asks the model to include important visible text (logos, signs, headings).
- **Fixed** estimated costs now include "thinking" tokens that some providers report outside the output count (Gemini's were missing, so Gemini costs were under-reported by about a third). Logs show them as **Thinking Tokens**, and the spending limit uses the corrected amounts.
- **Improved** Gemini now uses Google's Interactions API, which sends images at low resolution (280 tokens instead of 1,120), cutting Gemini's cost per image by roughly half or more. Requests are sent with `store: false`, so Google doesn't keep them for later retrieval.
- **New: alt text shows up in existing posts.** WordPress copies an image's alt text into a post when the image is inserted, so alt text generated later never reached older posts. EveryAlt now fills in empty alt text in post content from the Media Library as pages are displayed (WordPress 6.0+). Posts aren't modified, and alt text written in a post is never replaced. Off by default; turn it on under **Settings → Existing posts**.
- **New: background processing.** Bulk generation and new uploads now run in a background queue: close the tab and it keeps going, failures retry automatically (after 1, 5, and 30 minutes), and uploads finish immediately. The queue runs on WP-Cron and also whenever an EveryAlt page is open, so it works on sites where WP-Cron is blocked (e.g. behind HTTP auth). Bulk tabs gain a **Generate for all** button. Prefer the old behavior for uploads? Choose **Settings → New uploads → Generate during the upload**.
- **New: decorative image detection.** When the AI judges an image clearly decorative, its alt text is left empty on purpose (per WCAG) and it's flagged on the Review Alt Text tab with a **Describe anyway** button. When in doubt, the AI describes the image. Turn off under **Settings → Decorative images**.
- **New: spending controls.** The Logs tab shows estimated spend per month and per model. Set a **monthly spending limit** in Settings: when it's reached, generation pauses (queued images wait), admins see a notice, and it resumes next month or when the limit is raised.
- **New: alt text in your site's language.** Alt text and titles are now written in your site language, or each image's own language on multilingual sites using Polylang or WPML. Choose a specific language under **Settings → Language**.
- **New: choose your AI model.** Pick from **GPT-5.4 nano** (OpenAI), **Gemini 3.1 Flash-Lite** (Google), or **DeepSeek V4.1 Flash** / **GLM-5.3-Flash** (DeepInfra) in Settings. Keys are stored per provider, so you can switch without re-entering them. Settings shows each model's published pricing, step-by-step key instructions, and privacy details, including DeepInfra's US/Canada hosting and zero data retention.
- **Changed** OpenAI requests now use **GPT-5.4 nano**; `gpt-5-nano` is being retired. Existing installs switch automatically.
- **Added** a Model column to the Logs tab and CSV export.
- **Developer:** the `everyalt_openai_model` filter is replaced by `everyalt_model`; the price filters now also receive the model slug.
- **Fixed** automatic updates: release ZIPs now bundle the update checker library (it was missing from ZIPs built with `build.sh`).
- **Fixed** deactivating the plugin no longer deletes your API key and settings. Uninstalling now removes all EveryAlt data (settings, key, logs) instead of leaving it behind.
- **Fixed** "Auto-generate on upload" now starts checked on fresh installs, as intended.
- **Fixed** Editors and Authors can now use the block-editor and media-screen "Generate alt text" buttons on images they can edit, and failures now show a message instead of doing nothing.
- **Fixed** auto-generation on upload now runs exactly once per image, after all image sizes exist (it could previously retry once per image size after a failure).
- **Fixed** the 4MB limit now applies to the resized image actually sent to OpenAI, not the original upload, so large phone photos are no longer skipped.
- **Improved** the Bulk and Review tabs are paginated and much faster on large media libraries.
- **Improved** block-editor and media-screen button text is now translatable.
- **Added** a warning when the saved API key can no longer be decrypted (e.g. after the security keys in `wp-config.php` change).
- **Security** CSV log exports are protected against spreadsheet formula injection; the unused HTTP-auth username/password settings (the password was stored in plain text) have been removed and are deleted on update.
- **Removed** the unused `every_alt_logs` database table (dropped on uninstall) and the legacy `/get_tokens` REST endpoint.

### 1.0.2
- **New — AI image titles.** EveryAlt now writes descriptive WordPress image **titles**, not just alt text:
  - Optional **Automatically generate image titles when images are uploaded** toggle in Settings.
  - **Bulk Image Title Generator** tab — finds images whose title is still the raw upload filename (e.g. `IMG_1234`, stock-photo IDs, `ChatGPT Image …`) and titles them in bulk.
  - **Review Image Titles** tab — edit, save, or regenerate titles for images that already have a custom one.
  - A dedicated, editable **image title prompt** tuned for short titles.
- **Improved** detection of filename-style titles (underscores, hyphen slugs, duplicate `(1)` suffixes, camera/screenshot/stock/AI patterns, and random ID tokens) so the right images get surfaced for retitling.
- **Fixed** a PHP 8.2+ deprecation notice caused by a dynamic property (`$plugin_screen_hook_suffix`).
- **Fixed** admin CSS/JS caching — assets are now versioned by file-modification time, so updates load without a manual hard refresh.
- **Changed** the admin intro notice: simplified the copy and linked the EveryAlt name to [everyalt.com](https://everyalt.com).

### 1.0.1
- Added an automatic update checker so new releases appear in your WordPress dashboard.

### 1.0.0
- Initial release: AI alt text generation (auto-on-upload, bulk, review/edit, block-editor and media-screen buttons, logs with CSV export), encrypted OpenAI key storage, and translations for Spanish, Italian, Japanese, French, Brazilian Portuguese, German, and Dutch.

---

## 📄 License

GPLv2 or later. See [LICENSE](LICENSE.txt) for details.

---

## 💜 By [HDC](https://hdc.net)

EveryAlt is free, open, and maintained with care. If it helps your site be more accessible, we’re glad.

**EveryAlt 1.0 — free, unlimited, your key.**
