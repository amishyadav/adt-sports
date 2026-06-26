# ADT Sports → World-Class Roadmap

From "production-ready" to a world-class Kabaddi media platform. Sequenced in
dependency order and **measurable** — "world-class" is defined by targets, not vibes.

> Status: Phases 0–4 complete (security, tests, performance, features). 81 tests green.
> This document covers Phases 5–10.

## The bar (p75 field data)

| Dimension | Target | Stretch |
|---|---|---|
| LCP | ≤ 2.5s | ≤ 1.8s |
| INP | ≤ 200ms | ≤ 150ms |
| CLS | ≤ 0.1 | ≈ 0 |
| TTFB | ≤ 200ms (CDN + full-page cache) | ≤ 120ms |
| Lighthouse | Perf ≥ 95 · A11y ≥ 95 · SEO 100 · BP ≥ 95 | all ≥ 98 |
| Search latency | < 100ms, typo-tolerant | < 50ms |
| Image payload | −60–80% vs today | — |

## Guardrails (every phase)
- Tests stay green; each phase ships tests + an adversarial review.
- No CSP regression — move inline JS out rather than add more.
- No SPA rewrite. Blade stays; add a light Vite build + Alpine only where it pays.
- Measure before/after (Lighthouse CI + field RUM), don't assert.

---

## Phase 5 — Performance Polish & Asset Pipeline *(foundation)*
**Why first:** speed is the world-class feeling; it lifts SEO + retention; the asset
pipeline unblocks everything after it and resolves the inline-everything ceiling.

- Vite build: extract inline CSS/JS to hashed, minified, long-cache bundles; critical-CSS inline, rest deferred.
- Font strategy: `display=swap`, preconnect, async/non-blocking Font Awesome (or inline SVG icons).
- Image pipeline: WebP/AVIF + responsive widths on upload; `<img srcset sizes width height loading>`; LQIP blur-up.
- Full-page caching for anonymous reads (safe now that view-counts are buffered), busted by ArticleObserver.
- Lighthouse CI gate in GitHub Actions.

**Effort:** ~1.5 wk · **Success:** Lighthouse Perf ≥ 95 on home + article; responsive WebP LCP image; CLS ≈ 0; anon cache hit > 90%.

## Phase 6 — Editorial Excellence
**Why:** content velocity compounds; the `execCommand` editor is deprecated and emits messy HTML.
- Replace editor with TipTap/ProseMirror (clean HTML, `/`-commands, paste-cleanup, enforced alt, embeds); keep HTMLPurifier as the server guarantee.
- Autosave + draft recovery.
- Revisions / version history with diff + restore.
- Signed, expiring draft preview links (no login).
- Media library: alt/captions first-class, focal-point cropping.

**Effort:** ~2 wk · **Success:** editor emits purifier-clean HTML; alt required; autosave survives refresh; preview link works logged-out and expires.

## Phase 7 — Discovery & Search
- Instant search via Meilisearch/Typesense (Scout): search-as-you-type, typo-tolerant, faceted; FULLTEXT fallback.
- Smarter related/recommended (engine similarity) + "popular this week."
- Discovery surfaces: topic hubs, trending, editor's picks.

**Effort:** ~1.5 wk · **Success:** P95 search < 100ms; typo queries resolve; reindex on publish/trash via observer.

## Phase 8 — Growth & Distribution
- Analytics (GA4/Plausible) + popular-posts; newsletter capture + automated digest; web-push for breaking news; social auto-posting; sitemap index; expanded structured data.

**Effort:** ~2 wk · **Success:** analytics + events live; double-opt-in newsletter + digest; breaking-news push delivered.

## Phase 9 — The Differentiator: TSR Data Journalism *(the moat)*
- Structured match/player/team data; interactive visualizations (raid success, tackle heatmaps, season trends) as lazy-loaded components; embeddable stat widgets (oEmbed); player/match landing pages; data-backed article scaffolds.

**Effort:** 3–4 wk · **Success:** interactive stat page within CWV budget; embeddable widget renders on a third-party page; data pages indexed.

## Phase 10 — Edge & Reliability
- CDN + image CDN; edge/full-page cache tier; RUM / Web-Vitals field reporting; uptime + alerting; automated backups + tested restore (DR); read-replica readiness.

**Effort:** ~1 wk + ongoing · **Success:** global TTFB ≤ 200ms; real-user CWV green at p75; backup restore rehearsed.

---

## Sequencing (1 focused dev)
```
Wk 1–2   Phase 5  Asset pipeline + images + full-page cache + Lighthouse CI
Wk 3–4   Phase 6  Block editor + autosave + revisions + preview links
Wk 5–6   Phase 7  Instant search + discovery
Wk 7–8   Phase 8  Analytics + newsletter + web-push
Wk 9–12  Phase 9  TSR data journalism (the moat)
Wk 13    Phase 10 CDN + RUM + backups/DR
```

## Quick wins (before Phase 5 proper)
- Convert uploads to WebP + add width/height/loading to content images.
- preconnect + `display=swap` for fonts; async Font Awesome.
- Wire Lighthouse CI so every PR is measured.
