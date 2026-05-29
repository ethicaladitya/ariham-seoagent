=== Ariham SEOAgent ===
Contributors: ethicaladitya
Tags: seo, google-search-console, analytics, ai, automation
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Autonomous SEO agent: connects GSC, GA4, and your SEO plugins to continuously analyse, score, and fix your content on autopilot.

== Description ==

**Ariham SEOAgent is a hands-free SEO growth engine for WordPress.** It connects to Google Search Console and Google Analytics 4 to read real traffic and ranking data for every post on your site, then uses that data — optionally combined with Gemini or OpenAI — to detect problems, generate prioritised recommendations, and apply safe fixes automatically.

Unlike traditional SEO plugins that show you a checklist to fill in, Ariham SEOAgent acts like a background analyst that never sleeps: it scores every page on a 0–100 SEO scale, surfaces the highest-impact opportunities first, and can apply safe metadata updates for you on a schedule — with full rollback if anything looks wrong.

**Connect your existing tools — Ariham SEOAgent enhances what you already have:**

Ariham SEOAgent reads and writes to the SEO plugins you already use. It does not replace them.

* **Google Search Console** — pulls real queries, impressions, clicks, average position, and CTR per post so recommendations are based on actual search performance, not guesses.
* **Google Analytics 4** — pulls sessions, engagement rate, and average time-on-page to identify pages losing traffic or readers leaving quickly.
* **Google Site Kit** — if Site Kit is already installed and authorised, Ariham SEOAgent uses its connection bridge to access Search Console data without requiring a separate OAuth setup.
* **Yoast SEO** — reads and writes meta titles, descriptions, and focus keywords to Yoast's fields.
* **Rank Math** — reads and writes meta and focus keywords to Rank Math's meta keys.
* **SmartCrawl** — integrates with SmartCrawl's redirect manager and meta fields.
* **The SEO Framework** — reads and writes to TSF's post meta.
* **AIOSEO & SEOPress** — meta write support included.
* **Redirection plugin** — can create and manage 301 redirects through Redirection's database when it is active.

**What it does on a daily basis:**

1. Fetches fresh Search Console and GA4 data for all published posts.
2. Scores each post across seven SEO dimensions: metadata completeness, title/description optimisation opportunity, content depth, content freshness, search-intent alignment, internal linking, and image alt text.
3. Detects six signals per post: missing meta, optimisation opportunity, thin content, content refresh needed, intent mismatch, and declining performance.
4. Builds a prioritised queue of recommendations — each with a confidence score, a "safe vs risky" classification, and a before/after preview.
5. In manual mode: presents the queue in the Pending Approvals admin screen for you to approve or dismiss one by one.
6. In Autopilot mode: automatically applies recommendations classified as "safe" above a confidence threshold you configure, capped at a daily limit.
7. Every applied change is logged with full before/after values and is one-click reversible from the Rollback Center.

**Core features:**

* **Real-data scoring** — every decision is backed by actual Search Console and GA4 numbers, not keyword-density calculations.
* **AI-powered suggestions** — optional Gemini (Google AI) and OpenAI/OpenAI-compatible integrations generate keyword-rich meta titles, meta descriptions, and focus-keyword suggestions tuned to your existing ranking data.
* **Autopilot with guardrails** — safe-only auto-apply mode, configurable confidence floor, daily change limit, and instant rollback. You stay in control.
* **Full audit trail** — every recommendation, every applied fix, and every rollback is recorded in the Activity Log with timestamps, before/after values, confidence scores, and what triggered the change.
* **Internal link engine** — detects orphan pages and inserts contextual internal links to help distribute PageRank and reduce crawl depth.
* **Schema injection** — auto-injects Article, BlogPosting, FAQPage, HowTo, and BreadcrumbList JSON-LD structured data via `wp_head`.
* **404 monitoring and redirect management** — logs 404s and creates 301 redirects through whichever redirect plugin you already have active.
* **WP-CLI suite** — 10 CLI subcommands for power users: `analyze`, `optimize`, `report`, `rollback`, `fetch-gsc`, `fetch-ga4`, `score`, `opportunities`, `status`, `logs`.
* **Clean uninstall** — removes every custom table, option, post meta key, transient, and scheduled event when uninstalled.

**Privacy and data handling:**

No data leaves your server until you explicitly connect a Google account. No remote call is made on activation. OAuth tokens and API keys are stored encrypted (AES-256-CBC) in your WordPress database.

== Installation ==

1. Upload the `seo-agent-ai` folder to `/wp-content/plugins/`, or install via the WordPress Plugins screen.
2. Activate the plugin.
3. Go to **Ariham SEOAgent → Settings** and enter your Google OAuth Client ID and Client Secret.
   - Create a project at [Google Cloud Console](https://console.cloud.google.com/), enable the Search Console API and Google Analytics Data API, and add your wp-admin Connect Google page URL as an authorised redirect URI.
4. Go to **Ariham SEOAgent → Connect Google** and complete the OAuth flow.
5. In Settings, select your verified Search Console property and your GA4 property.
6. Optionally paste a Gemini or OpenAI API key to enable AI-powered meta generation.
7. Click **Run Full Scan** once to seed data. Daily WP-Cron keeps everything fresh from then on.

== Frequently Asked Questions ==

= Does it automatically change my live posts without me knowing? =

No — not by default. In the default manual mode you must click "Apply" on each recommendation in the Pending Approvals screen. Autopilot mode is an explicit opt-in setting and only applies changes classified as "safe" above the confidence threshold you set, capped at a daily limit you choose.

= Do I need to have a specific SEO plugin installed? =

No. Ariham SEOAgent works standalone and writes meta to its own post-meta keys. If you have Yoast SEO, Rank Math, SmartCrawl, The SEO Framework, AIOSEO, or SEOPress installed and active, the plugin will also write to those plugins' meta fields automatically, so your existing SEO plugin always shows the correct values.

= Does it send any data to external servers before I configure it? =

No. The plugin makes zero remote calls until you provide OAuth credentials and complete the Google connection. The Gemini and OpenAI integrations are only invoked after you save an API key. No telemetry, no phone-home.

= Where are my API keys and OAuth tokens stored? =

In your WordPress database, encrypted at rest with AES-256-CBC using a key derived from your site's `wp_salt('secure_auth')`. They never leave your server except when making authorised API requests.

= Which SEO plugins does it read from and write to? =

Yoast SEO, Rank Math, SmartCrawl, The SEO Framework, AIOSEO, and SEOPress — whichever are active. It detects them automatically and writes to all of them at once, so you never have a mismatch between plugins.

= How do I undo a change? =

Open **Ariham SEOAgent → Rollback Center**, find the entry, and click Rollback. The previous meta value is restored across every SEO plugin that was written to.

= Will it slow down my site? =

No. All analysis and API calls happen inside WP-Cron jobs that run in the background. Nothing runs on the front-end. Admin pages load data from local database tables, not live API calls.

= Does it work with Google Site Kit? =

Yes. If Google Site Kit is installed and already authorised with your Google account, Ariham SEOAgent detects it automatically and uses its connection bridge to pull Search Console data — no separate OAuth credentials required. You can still set up the dedicated OAuth flow if you prefer, or if you need GA4 data that Site Kit does not expose.

== External Services ==

This plugin communicates with the following third-party services **only after you provide credentials and authorise the connection**. No external call is made on plugin activation or on the front-end.

**Google APIs (required for core functionality)**

After you complete the Google OAuth flow, the plugin communicates with:

* `accounts.google.com` — OAuth 2.0 authorisation and token exchange
* `oauth2.googleapis.com` — Access-token refresh
* `searchconsole.googleapis.com` — Search Console query data (impressions, clicks, position, CTR) for your verified property
* `analyticsdata.googleapis.com` — GA4 sessions, engagement rate, and time-on-page
* `analyticsadmin.googleapis.com` — Listing your GA4 properties

[Google Privacy Policy](https://policies.google.com/privacy) | [Google Terms of Service](https://policies.google.com/terms)

**Google Gemini AI (optional)**

When you save a Gemini API key, AI-generated meta suggestions are fetched from `generativelanguage.googleapis.com`.

[Gemini API Terms](https://ai.google.dev/gemini-api/terms)

**OpenAI / OpenAI-compatible endpoint (optional)**

When you configure an OpenAI API key, meta suggestions are fetched from `api.openai.com` (or a custom base URL you specify). This can point to any OpenAI-compatible provider.

[OpenAI Privacy Policy](https://openai.com/policies/privacy-policy) | [OpenAI Terms of Use](https://openai.com/policies/terms-of-use)

No user data is sent to any third-party service until you explicitly configure and activate that integration.

== Screenshots ==

1. Dashboard — activity feed, score distribution, traffic trends, and key metrics at a glance.
2. Opportunities — prioritised list of SEO recommendations with confidence scores and before/after previews.
3. Pending Approvals — review and apply AI-generated meta improvements one at a time or in bulk.
4. Connect Google — step-by-step OAuth flow for Search Console and GA4.
5. Settings — configure AI provider, autopilot mode, confidence threshold, and daily change cap.
6. Rollback Center — full audit trail with one-click restore for every applied change.
7. Cron Status — real-time view of all background jobs and manual trigger controls.

== Changelog ==

= 0.0.1 =
* Initial release.

== Upgrade Notice ==

= 0.0.1 =
Initial release.
