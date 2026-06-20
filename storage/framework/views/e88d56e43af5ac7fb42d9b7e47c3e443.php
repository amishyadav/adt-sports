<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo $__env->yieldContent('title', ($settings['site_name'] ?? 'ADT Sports')); ?></title>
<meta name="description" content="<?php echo $__env->yieldContent('meta_desc', $settings['site_description'] ?? "India's #1 Kabaddi media platform."); ?>">


<?php
    $seoSiteName  = $settings['site_name'] ?? 'ADT Sports';
    $seoTitle     = trim($__env->yieldContent('title', $seoSiteName));
    $seoDesc      = trim($__env->yieldContent('meta_desc', $settings['site_description'] ?? "India's #1 Kabaddi media platform."));
    $seoCanonical = trim($__env->yieldContent('canonical')) ?: url()->current();
    $seoRobots    = trim($__env->yieldContent('robots', 'index, follow'));
    $seoType      = trim($__env->yieldContent('og_type', 'website'));
    $seoImage     = trim($__env->yieldContent('og_image')) ?: '/public/uploads/logo.png';
    if (! \Illuminate\Support\Str::startsWith($seoImage, ['http://', 'https://'])) {
        $seoImage = url($seoImage);
    }
    $seoSocials = array_values(array_filter([
        $settings['facebook_url']  ?? null,
        $settings['instagram_url'] ?? null,
        $settings['youtube_url']   ?? null,
        $settings['twitter_url']   ?? null,
    ]));
?>
<link rel="canonical" href="<?php echo e($seoCanonical); ?>">
<meta name="robots" content="<?php echo e($seoRobots); ?>">
<link rel="alternate" type="application/rss+xml" title="<?php echo e($seoSiteName); ?> RSS" href="<?php echo e(url('/feed.xml')); ?>">
<?php echo $__env->yieldPushContent('head_links'); ?>


<meta property="og:type" content="<?php echo e($seoType); ?>">
<meta property="og:site_name" content="<?php echo e($seoSiteName); ?>">
<meta property="og:title" content="<?php echo e($seoTitle); ?>">
<meta property="og:description" content="<?php echo e($seoDesc); ?>">
<meta property="og:url" content="<?php echo e($seoCanonical); ?>">
<meta property="og:image" content="<?php echo e($seoImage); ?>">
<?php echo $__env->yieldPushContent('og_meta'); ?>


<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo e($seoTitle); ?>">
<meta name="twitter:description" content="<?php echo e($seoDesc); ?>">
<meta name="twitter:image" content="<?php echo e($seoImage); ?>">
<?php if(!empty($settings['twitter_url'])): ?>
<meta name="twitter:site" content="<?php echo e('@' . \Illuminate\Support\Str::afterLast(rtrim($settings['twitter_url'], '/'), '/')); ?>">
<?php endif; ?>


<script type="application/ld+json">
<?php echo json_encode([
    '@context'    => 'https://schema.org',
    '@type'       => 'Organization',
    'name'        => $seoSiteName,
    'url'         => url('/'),
    'logo'        => url('/public/uploads/logo.png'),
    'description' => $settings['site_description'] ?? "India's #1 Kabaddi media platform.",
] + (count($seoSocials) ? ['sameAs' => $seoSocials] : []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>

</script>
<script type="application/ld+json">
<?php echo json_encode([
    '@context'        => 'https://schema.org',
    '@type'           => 'WebSite',
    'name'            => $seoSiteName,
    'url'             => url('/'),
    'potentialAction' => [
        '@type'       => 'SearchAction',
        'target'      => url('/search') . '?q={search_term_string}',
        'query-input' => 'required name=search_term_string',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>

</script>
<?php echo $__env->yieldPushContent('schema'); ?>

  <link rel="shortcut icon" href="/public/uploads/logo.png" data-inertia="favicon-shortcut">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,700;0,800;1,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
:root{--bg:#FAF8F5;--bg2:#F3F0EB;--bg3:#EAE6DF;--surface:#FFF;--ink:#1C1917;--ink2:#44403C;--ink3:#78716C;--ink4:#A8A29E;--rule:rgba(28,25,23,.09);--rule2:rgba(28,25,23,.05);--brand:#D4420A;--brand-h:#B83808;--brand-soft:rgba(212,66,10,.09);--green:#16803C;--amber:#B45309;--serif:'Lora',Georgia,serif;--display:'Playfair Display',Georgia,serif;--sans:'Inter',system-ui,sans-serif;--nav-h:58px;--max:1180px}
[data-theme="dark"]{--bg:#131110;--bg2:#1C1917;--bg3:#252220;--surface:#1C1917;--ink:#F5F0EB;--ink2:#C2BAB3;--ink3:#78716C;--ink4:#44403C;--rule:rgba(245,240,235,.07);--rule2:rgba(245,240,235,.04);--brand-soft:rgba(212,66,10,.13)}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}html{scroll-behavior:smooth}
body{background:var(--bg);color:var(--ink);font-family:var(--sans);line-height:1.6;transition:background .25s,color .25s;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}img{display:block;max-width:100%}button{cursor:pointer;border:none;background:none;font:inherit;color:inherit}ul{list-style:none}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
::-webkit-scrollbar{width:3px}::-webkit-scrollbar-track{background:var(--bg)}::-webkit-scrollbar-thumb{background:var(--brand);border-radius:2px}
::selection{background:var(--brand-soft);color:var(--brand)}
#readBar{position:fixed;top:0;left:0;height:2.5px;background:linear-gradient(90deg,var(--brand),#F07820);width:0%;z-index:9999;pointer-events:none;transition:width .08s}

/* TICKER */
.ticker-strip {
  background: #1c1917;
  color: #fff;
  font-size: 12px;
  padding: 7px 0;
}

.ticker-container {
  max-width: var(--max);
  margin: 0 auto;
  padding: 0 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  overflow: hidden;
}

.ticker-label {
  flex-shrink: 0;
  background: var(--brand);
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  padding: 2px 10px;
  border-radius: 2px;
}

.ticker-wrapper {
  flex: 1;
  overflow: hidden;
}

.ticker-inner {
  display: inline-flex;
  white-space: nowrap;
  animation: tick 30s linear infinite;
}

.ticker-inner:hover {
  animation-play-state: paused;
}

.ticker-item {
  padding: 0 32px;
  color: rgba(255,255,255,.7);
}

.ticker-item strong {
  color: #fff;
}

.ticker-sep {
  color: var(--brand);
  padding: 0 4px;
}

@keyframes tick {
  from {
    transform: translateX(0);
  }
  to {
    transform: translateX(-50%);
  }
}

/* NAV */
nav{position:sticky;top:0;z-index:500;background:var(--surface);border-bottom:1px solid var(--rule);height:var(--nav-h);transition:box-shadow .3s,background .25s}
nav.shadow{box-shadow:0 1px 18px rgba(0,0,0,.07)}
.nav-wrap{max-width:var(--max);margin:0 auto;padding:0 20px;height:100%;display:flex;align-items:center}
.logo{display:flex;align-items:center;gap:10px;flex-shrink:0;padding-right:24px;border-right:1px solid var(--rule);cursor:pointer}
.logo-img{width:36px;height:36px;border-radius:50%;background:var(--ink);overflow:hidden;flex-shrink:0}
.logo-img img{width:100%;height:100%;}
.logo-wordmark{font-family:var(--sans);font-size:16px;font-weight:700}.logo-wordmark .brand{color:var(--brand)}
.nav-links{display:flex;align-items:center;flex:1;padding:0 12px;gap:2px;overflow:hidden}
.nav-links a{padding:7px 13px;font-size:13.5px;font-weight:500;color:var(--ink2);border-radius:6px;white-space:nowrap;transition:color .15s,background .15s}
.nav-links a:hover,.nav-links a.active{color:var(--brand);background:var(--brand-soft)}
.nav-drop{position:relative}
.nav-drop:hover .drop-menu{opacity:1;visibility:visible;transform:translateY(0)}
.drop-menu{position:absolute;top:calc(100% + 8px);left:0;background:var(--surface);border:1px solid var(--rule);border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,.10);min-width:190px;padding:6px 0;opacity:0;visibility:hidden;transform:translateY(-6px);transition:all .2s;z-index:600}
.drop-menu a{display:block;padding:9px 18px;font-size:13px;color:var(--ink2)}.drop-menu a:hover{color:var(--brand);background:var(--brand-soft)}
.nav-right{display:flex;align-items:center;gap:6px;padding-left:14px;border-left:1px solid var(--rule);flex-shrink:0;margin-left:auto}
.icon-btn{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;background:var(--bg2);border:1px solid var(--rule);transition:background .15s;cursor:pointer}
.icon-btn:hover{background:var(--brand-soft);border-color:var(--brand)}
.btn-sub{background:var(--brand);color:#fff;padding:7px 16px;border-radius:20px;font-size:13px;font-weight:600;transition:background .15s;text-decoration:none}
.btn-sub:hover{background:var(--brand-h)}
.hamburger{display:none;flex-direction:column;gap:4px;width:34px;height:34px;align-items:center;justify-content:center}
.hamburger span{display:block;width:18px;height:2px;background:var(--ink2);border-radius:2px}

/* SEARCH */
.search-overlay{position:fixed;inset:0;background:rgba(19,17,16,.88);backdrop-filter:blur(16px);z-index:800;display:flex;align-items:flex-start;justify-content:center;padding-top:100px;opacity:0;visibility:hidden;transition:all .25s}
.search-overlay.open{opacity:1;visibility:visible}
.search-box{width:100%;max-width:580px;padding:0 20px}
.search-row{display:flex;align-items:center;gap:12px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);border-radius:12px;padding:14px 18px}
.search-row input{flex:1;background:none;border:none;outline:none;font-family:var(--sans);font-size:20px;color:#F5F0EB;font-weight:300}
.search-row input::placeholder{color:rgba(245,240,235,.3)}
.s-icon{color:rgba(245,240,235,.4);font-size:18px}.s-close{color:rgba(245,240,235,.4);font-size:20px;cursor:pointer;transition:color .15s}.s-close:hover{color:#fff}
.search-hint{margin-top:14px;font-size:12px;color:rgba(245,240,235,.3);text-align:center;letter-spacing:.5px}

/* MOBILE NAV */
.mobile-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:699;opacity:0;visibility:hidden;transition:.3s}
.mobile-overlay.open{opacity:1;visibility:visible}
.mobile-nav{position:fixed;top:0;right:0;bottom:0;width:280px;background:var(--surface);border-left:1px solid var(--rule);z-index:700;padding:70px 24px 32px;transform:translateX(100%);transition:transform .3s;overflow-y:auto}
.mobile-nav.open{transform:translateX(0)}
.mobile-nav a{display:block;padding:12px 0;font-size:15px;font-weight:500;color:var(--ink2);border-bottom:1px solid var(--rule2);transition:color .15s}
.mobile-nav a:hover{color:var(--brand)}

/* LAYOUT */
.wrap{max-width:var(--max);margin:0 auto;padding:0 20px}

/* CAT TABS */
.cat-tabs{display:flex;gap:4px;padding:22px 0 4px;overflow-x:auto;scrollbar-width:none}
.cat-tabs::-webkit-scrollbar{display:none}
.ctab{background:var(--surface);border:1px solid var(--rule);color:var(--ink2);font-size:13px;font-weight:500;padding:6px 16px;border-radius:20px;white-space:nowrap;cursor:pointer;transition:all .15s;text-decoration:none;display:inline-block}
.ctab:hover,.ctab.active{background:var(--ink);border-color:var(--ink);color:var(--bg)}
[data-theme="dark"] .ctab:hover,[data-theme="dark"] .ctab.active{background:var(--brand);border-color:var(--brand);color:#fff}

/* HERO */
.home-hero{display:grid;grid-template-columns:1fr 340px;gap:1px;background:var(--rule);border:1px solid var(--rule);border-radius:12px;overflow:hidden;margin-top:28px}
.hero-lead{position:relative;cursor:pointer;overflow:hidden;background:var(--bg3);aspect-ratio:16/10}
.hero-lead-art{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:90px;transition:transform .5s}
.hero-lead:hover .hero-lead-art{transform:scale(1.03)}
.hero-lead-veil{position:absolute;inset:0;background:linear-gradient(to top,rgba(10,8,6,.93) 0%,rgba(10,8,6,.35) 55%,transparent 100%)}
.hero-lead-body{position:absolute;bottom:0;left:0;right:0;padding:24px 28px}
.cat-pill{display:inline-block;background:var(--brand);color:#fff;font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:3px 10px;border-radius:3px;margin-bottom:10px}
.hero-lead-title{font-family:var(--display);font-size:clamp(20px,2.8vw,30px);font-weight:700;line-height:1.25;color:#F5F0EB;margin-bottom:10px}
.hero-lead-meta{font-size:12px;color:rgba(245,240,235,.5);display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.hero-lead-meta .sep{width:3px;height:3px;background:var(--brand);border-radius:50%}
.hero-stack{background:var(--surface);display:flex;flex-direction:column}
.hero-stack-item{flex:1;padding:16px 18px;border-bottom:1px solid var(--rule);cursor:pointer;display:flex;gap:13px;align-items:flex-start;transition:background .15s}
.hero-stack-item:last-child{border-bottom:none}.hero-stack-item:hover{background:var(--bg2)}
.stack-thumb{width:68px;height:68px;border-radius:6px;flex-shrink:0;overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:28px}
.stack-cat{font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--brand);margin-bottom:4px}
.stack-title{font-family:var(--serif);font-size:14.5px;font-weight:600;line-height:1.35;color:var(--ink);margin-bottom:5px;transition:color .15s}
.hero-stack-item:hover .stack-title{color:var(--brand)}
.stack-meta{font-size:11px;color:var(--ink3)}

/* CONTENT GRID */
.content-grid{display:grid;grid-template-columns:1fr 300px;gap:44px;padding-top:36px}
.sec-hd{display:flex;align-items:center;justify-content:space-between;padding-bottom:11px;border-bottom:2px solid var(--ink);margin-bottom:20px}
[data-theme="dark"] .sec-hd{border-bottom-color:var(--ink3)}
.sec-hd-left{display:flex;align-items:center;gap:9px}
.sec-hd-bar{width:3px;height:20px;background:var(--brand);border-radius:2px}
.sec-hd-label{font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--ink)}
.sec-hd-more{font-size:12px;font-weight:500;color:var(--brand);display:flex;align-items:center;gap:3px;transition:gap .15s}
.sec-hd-more:hover{gap:7px}

/* ARTICLE CARDS */
.card-row{display:grid;grid-template-columns:1fr 220px;gap:18px;padding:22px 0;border-bottom:1px solid var(--rule2)}
.card-row:first-child{padding-top:0}.card-row:last-child{border-bottom:none}
.cr-cat{font-size:10.5px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--brand);margin-bottom:7px;display:block}
.cr-title{font-family:var(--display);font-size:20px;font-weight:700;line-height:1.28;color:var(--ink);margin-bottom:8px;transition:color .15s}
.card-row:hover .cr-title{color:var(--brand)}
.cr-excerpt{font-family:var(--serif);font-size:14.5px;line-height:1.68;color:var(--ink2);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:11px}
.cr-meta{display:flex;align-items:center;gap:7px;font-size:11.5px;color:var(--ink3)}
.cr-meta .sep{width:3px;height:3px;background:var(--ink4);border-radius:50%}
.cr-thumb{height:148px;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:52px;align-self:flex-start;flex-shrink:0;transition:transform .3s}
.card-row:hover .cr-thumb{transform:scale(1.02)}
.cards-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-bottom:40px}
.card-box{}
.cb-thumb{width:100%;aspect-ratio:16/10;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:44px;margin-bottom:11px;transition:transform .3s}
.card-box:hover .cb-thumb{transform:scale(1.02)}
.cb-cat{font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--brand);display:block;margin-bottom:5px}
.cb-title{font-family:var(--display);font-size:16px;font-weight:700;line-height:1.3;color:var(--ink);margin-bottom:6px;transition:color .15s}
.card-box:hover .cb-title{color:var(--brand)}
.cb-excerpt{font-size:13px;line-height:1.6;color:var(--ink2);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:8px}
.cb-meta{font-size:11px;color:var(--ink3)}

/* FEATURE STRIP */
.feature-strip{border-radius:10px;padding:28px;margin:36px 0;background:linear-gradient(135deg,#141008,#1C1610);border:1px solid rgba(255,255,255,.06);display:grid;grid-template-columns:1fr 1fr 1fr;gap:0}
.fs-item{padding:0 24px;border-right:1px solid rgba(255,255,255,.07)}
.fs-item:first-child{padding-left:0}.fs-item:last-child{border-right:none;padding-right:0}
.fs-cat{font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--brand);margin-bottom:7px}
.fs-title{font-family:var(--serif);font-size:15px;font-weight:600;line-height:1.4;color:#E8E3DA;margin-bottom:5px;transition:color .15s}
.fs-item:hover .fs-title{color:#F07820}
.fs-meta{font-size:11px;color:rgba(245,240,235,.35)}

/* SIDEBAR */
.widget{background:var(--surface);border:1px solid var(--rule);border-radius:10px;padding:20px;margin-bottom:24px}
.widget-nl{background:linear-gradient(145deg,#161210,#201A12);border:1px solid rgba(255,255,255,.06)}
.widget-nl .sec-hd{border-bottom-color:rgba(255,255,255,.1)}.widget-nl .sec-hd-label{color:#F0EBE5}
.nl-desc{font-size:13.5px;color:rgba(240,235,229,.55);line-height:1.65;margin-bottom:14px}
.nl-input{width:100%;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:7px;padding:10px 13px;font-family:var(--sans);font-size:13px;color:#F0EBE5;outline:none;margin-bottom:9px;transition:border-color .2s}
.nl-input::placeholder{color:rgba(240,235,229,.28)}.nl-input:focus{border-color:var(--brand)}
.nl-btn{width:100%;background:var(--brand);color:#fff;padding:10px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s;border:none}
.nl-btn:hover{background:var(--brand-h)}
.tag-cloud{display:flex;flex-wrap:wrap;gap:7px;margin-top:4px}
.tag{background:var(--bg2);border:1px solid var(--rule);color:var(--ink2);font-size:12px;padding:4px 12px;border-radius:20px;cursor:pointer;transition:all .15s;text-decoration:none;display:inline-block}
.tag:hover{background:var(--brand-soft);border-color:var(--brand);color:var(--brand)}
.card-num{display:flex;gap:13px;padding:13px 0;border-bottom:1px solid var(--rule2)}
.card-num:last-child{border-bottom:none}
.cn-num{font-family:var(--display);font-size:22px;font-weight:700;color:var(--rule);line-height:1;flex-shrink:0;min-width:28px;transition:color .15s}
.card-num:hover .cn-num{color:var(--brand)}
.cn-title{font-family:var(--serif);font-size:13.5px;font-weight:600;line-height:1.4;color:var(--ink);margin-bottom:4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;transition:color .15s}
.card-num:hover .cn-title{color:var(--brand)}
.cn-meta{font-size:11px;color:var(--ink3)}
.about-mini-logo{display:flex;align-items:center;gap:9px;margin-bottom:12px}
.am-img{width:40px;height:40px;border-radius:50%;background:var(--ink);overflow:hidden;flex-shrink:0}
.am-img img{width:100%;height:100%;}
.am-name{font-weight:700;font-size:15px}
.am-name span{color:var(--brand)}
.about-mini-desc{font-size:13.5px;color:var(--ink2);line-height:1.65;margin-bottom:14px}
.socials-row{display:flex;gap:7px}
.soc-btn{display:flex;align-items:center;gap:5px;background:var(--bg2);border:1px solid var(--rule);border-radius:6px;padding:5px 10px;font-size:12px;color:var(--ink2);transition:all .15s;cursor:pointer}
.soc-btn:hover{background:var(--brand-soft);border-color:var(--brand);color:var(--brand)}

/* PAGINATION */
.pagination-wrap{text-align:center;padding:28px 0 44px}
.pagination-wrap .page-link{display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;padding:0 10px;border-radius:6px;margin:0 2px;background:var(--surface);border:1px solid var(--rule);color:var(--ink2);font-size:13px;font-weight:500;transition:all .15s;text-decoration:none}
.pagination-wrap .page-link:hover{border-color:var(--brand);color:var(--brand)}
.pagination-wrap .active .page-link{background:var(--brand);border-color:var(--brand);color:#fff}

/* ARTICLE */
.article-wrap{max-width:var(--max);margin:0 auto;padding:0 20px;display:grid;grid-template-columns:1fr 280px;gap:52px;padding-top:36px}
.article-main{max-width:680px}
.back-btn{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:500;color:var(--ink3);margin-bottom:24px;cursor:pointer;transition:color .15s;text-decoration:none}
.back-btn:hover{color:var(--brand)}
.art-hero-img{width:100%;aspect-ratio:16/8;border-radius:10px;overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:110px;margin-bottom:28px;position:relative}
.art-hero-img img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.art-cat{display:inline-block;background:var(--brand);color:#fff;font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:4px 11px;border-radius:3px;margin-bottom:14px}
.art-title{font-family:var(--display);font-size:clamp(26px,4vw,40px);font-weight:800;line-height:1.18;color:var(--ink);margin-bottom:16px}
.art-deck{font-family:var(--serif);font-size:18px;font-weight:400;font-style:italic;color:var(--ink2);line-height:1.65;border-left:3px solid var(--brand);padding-left:18px;margin-bottom:22px}
.art-byline{display:flex;align-items:center;gap:13px;padding:15px 0;border-top:1px solid var(--rule2);border-bottom:1px solid var(--rule2);margin-bottom:32px}
.byline-av{width:38px;height:38px;border-radius:50%;background:var(--brand-soft);border:2px solid var(--brand);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.byline-name{font-size:14px;font-weight:600;color:var(--ink)}
.byline-info{font-size:12px;color:var(--ink3)}
.byline-actions{margin-left:auto;display:flex;gap:7px}
.action-btn{width:32px;height:32px;border-radius:50%;background:var(--bg2);border:1px solid var(--rule);display:flex;align-items:center;justify-content:center;font-size:13px;cursor:pointer;transition:all .15s}
.action-btn:hover{background:var(--brand-soft);border-color:var(--brand)}
.art-body{font-family:var(--serif);font-size:18px;line-height:1.88;color:var(--ink2)}
.art-body p{margin-bottom:26px}
.art-body h2{font-family:var(--display);font-size:26px;font-weight:700;color:var(--ink);margin:44px 0 16px;line-height:1.22}
.art-body h3{font-family:var(--display);font-size:20px;font-weight:600;color:var(--ink);margin:34px 0 12px}
.art-body blockquote{margin:32px 0;padding:18px 24px;background:var(--bg2);border-left:4px solid var(--brand);border-radius:0 8px 8px 0;font-style:italic;font-size:19px;color:var(--ink);line-height:1.6}
.art-body strong{color:var(--ink);font-weight:600}
.art-body ul,.art-body ol{padding-left:24px;margin-bottom:20px}
.art-body li{margin-bottom:6px}
.art-body .callout{background:var(--brand-soft);border:1px solid rgba(212,66,10,.14);border-radius:8px;padding:18px 22px;margin:28px 0;font-size:15.5px;line-height:1.65;font-family:var(--sans)}
.art-sidebar-sticky{position:sticky;top:calc(var(--nav-h) + 20px)}
.related-section{margin-top:52px;padding-top:32px;border-top:1px solid var(--rule2)}

/* FOOTER */
footer{background:#1c1917;color:rgba(245,240,235,.65);margin-top:60px}
.footer-grid{max-width:var(--max);margin:0 auto;padding:44px 20px 32px;display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr;gap:44px;border-bottom:1px solid rgba(255,255,255,.06)}
.ft-logo{display:flex;align-items:center;gap:9px;margin-bottom:14px}
.ft-logo .fl-img{width:34px;height:34px;border-radius:50%;background:#333;overflow:hidden}
.ft-logo .fl-img img{width:100%;height:100%;}
.ft-logo span{font-size:15px;font-weight:700;color:#F5F0EB}.ft-logo span em{font-style:normal;color:var(--brand)}
.ft-desc{font-size:13.5px;line-height:1.7;margin-bottom:18px;color:rgba(245,240,235,.45)}
.ft-socials{display:flex;gap:7px}
.ft-soc{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;font-size:14px;cursor:pointer;transition:all .15s}
.ft-soc:hover{background:var(--brand);border-color:var(--brand)}
.icon-facebook:hover{background:#1877F2 !important;border-color:#1877F2 !important}
.icon-instagram:hover{background:#fa7e1e !important;border-color:#fa7e1e !important}
.icon-youtube:hover{background:#FF0033 !important;border-color:#FF0033 !important}
.icon-twitter:hover{background:#1DA1F2 !important;border-color:#1DA1F2 !important}
.ft-col h4{font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#F5F0EB;margin-bottom:16px}
.ft-col li{margin-bottom:9px}
.ft-col a{font-size:13.5px;color:rgba(245,240,235,.45);transition:color .15s}.ft-col a:hover{color:var(--brand)}
.footer-bottom{max-width:var(--max);margin:0 auto;padding:18px 20px;display:flex;align-items:center;justify-content:space-between;font-size:12.5px;color:rgba(245,240,235,.25);flex-wrap:wrap;gap:8px}
.footer-tagline{font-style:italic;color:rgba(245,240,235,.35);font-family:var(--serif)}

@media(max-width:1060px){.content-grid{grid-template-columns:1fr}.sidebar-col{display:none}.article-wrap{grid-template-columns:1fr}.art-sidebar{display:none}.feature-strip{grid-template-columns:1fr}.fs-item{border-right:none;border-bottom:1px solid rgba(255,255,255,.07);padding:14px 0}.fs-item:last-child{border-bottom:none}.footer-grid{grid-template-columns:1fr 1fr;gap:32px}.home-hero{grid-template-columns:1fr}}
@media(max-width:768px){.nav-links{display:none}.btn-sub{display:none}.hamburger{display:flex}.card-row{grid-template-columns:1fr}.cr-thumb{width:100%;height:190px}.cards-grid{grid-template-columns:1fr 1fr}.footer-grid{grid-template-columns:1fr}.footer-bottom{flex-direction:column;text-align:center}}
@media(max-width:480px){.cards-grid{grid-template-columns:1fr}.art-title{font-size:26px}.art-body{font-size:17px}}
</style>
<?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body>
<div id="readBar"></div>

<div class="search-overlay" id="srchOverlay">
  <div class="search-box">
    <div class="search-row">
      <span class="s-icon">🔍</span>
      <form action="<?php echo e(route('search')); ?>" method="GET" style="flex:1;display:flex">
        <input type="text" name="q" placeholder="Search Kabaddi news, players, leagues…" autofocus
          value="<?php echo e(request('q')); ?>" style="flex:1">
      </form>
      <button class="s-close" onclick="document.getElementById('srchOverlay').classList.remove('open')">✕</button>
    </div>
    <p class="search-hint">Press ESC to close · Enter to search</p>
  </div>
</div>

<div class="mobile-overlay" id="mobileOverlay"></div>
<div class="mobile-nav" id="mobileNav">
  <a href="<?php echo e(route('home')); ?>">🏠 Home</a>
  <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e(route('category', $cat->slug)); ?>"><?php echo e($cat->name); ?></a>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  <a href="<?php echo e(route('search')); ?>">🔍 Search</a>
</div>


<div class="ticker-strip">
  <div class="ticker-container">
    <span class="ticker-label">Live</span>

    <?php
      $ticker = $settings['breaking_ticker'] ?? 'ADT Sports — India\'s #1 Kabaddi Platform';
      $items = array_filter(array_map('trim', explode('|', $ticker)));
      $doubled = array_merge($items, $items);
    ?>

    <div class="ticker-wrapper">
      <div class="ticker-inner">
        <?php $__currentLoopData = $doubled; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <span class="ticker-item"><?php echo e($t); ?></span>
          <span class="ticker-sep">◆</span>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  </div>
</div>


<nav id="mainNav">
  <div class="nav-wrap">
    <a href="<?php echo e(route('home')); ?>" class="logo">
      <div class="logo-img"><img src="/public/uploads/logo.png" onerror="this.style.display='none'" alt="ADT"></div>
      <div class="logo-wordmark"><span class="brand">ADT</span> Sports</div>
    </a>
    <div class="nav-links">
      <a href="<?php echo e(route('home')); ?>" class="<?php echo e(request()->routeIs('home') && !request('category') ? 'active':''); ?>">Home</a>
      <?php $__currentLoopData = $categories->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(route('category',$cat->slug)); ?>"
          class="<?php echo e(request()->route('slug')===$cat->slug ? 'active':''); ?>"><?php echo e($cat->name); ?></a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      <?php if($categories->count() > 5): ?>
      <div class="nav-drop">
        <a href="#">More <span style="font-size:9px;opacity:.5">▾</span></a>
        <div class="drop-menu">
          <?php $__currentLoopData = $categories->skip(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e(route('category',$cat->slug)); ?>"><?php echo e($cat->name); ?></a>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <div class="nav-right">
      <button class="icon-btn" onclick="document.getElementById('srchOverlay').classList.add('open')"><i class="fa-solid fa-magnifying-glass"></i></button>
      <button class="icon-btn" id="themeBtn"><i class="fa-solid fa-moon"></i></button>
      <a href="#newsletter" class="btn-sub">Subscribe</a>
      <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
    </div>
  </div>
</nav>

<?php echo $__env->yieldContent('content'); ?>


<footer>
  <div class="footer-grid">
    <div>
      <div class="ft-logo">
        <div class="fl-img"><img src="/public/uploads/logo.png" onerror="this.style.display='none'" alt="ADT"></div>
        <span><em>ADT</em> Sports</span>
      </div>
      <p class="ft-desc"><?php echo e($settings['site_description'] ?? "India's #1 Kabaddi-focused digital media brand."); ?></p>
      <div class="ft-socials">
        <?php if(!empty($settings['facebook_url'])): ?>  <a href="<?php echo e($settings['facebook_url']); ?>"  target="_blank" class="ft-soc icon-facebook"><i class="fa-brands fa-facebook"></i></a> <?php endif; ?>
        <?php if(!empty($settings['instagram_url'])): ?> <a href="<?php echo e($settings['instagram_url']); ?>" target="_blank" class="ft-soc icon-instagram"><i class="fa-brands fa-instagram"></i></a> <?php endif; ?>
        <?php if(!empty($settings['youtube_url'])): ?>   <a href="<?php echo e($settings['youtube_url']); ?>"   target="_blank" class="ft-soc icon-youtube"><i class="fa-brands fa-youtube"></i></a>  <?php endif; ?>
        <?php if(!empty($settings['twitter_url'])): ?>   <a href="<?php echo e($settings['twitter_url']); ?>"   target="_blank" class="ft-soc icon-twitter"><i class="fa-brands fa-twitter"></i></a> <?php endif; ?>
      </div>
    </div>
    <div class="ft-col">
      <h4>Coverage</h4>
      <ul>
        <?php $__currentLoopData = $categories->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <li><a href="<?php echo e(route('category',$cat->slug)); ?>"><?php echo e($cat->name); ?></a></li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </ul>
    </div>
    <div class="ft-col">
      <h4>More</h4>
      <ul>
        <?php $__currentLoopData = $categories->skip(5)->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <li><a href="<?php echo e(route('category',$cat->slug)); ?>"><?php echo e($cat->name); ?></a></li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <li><a href="<?php echo e(route('search')); ?>">Search</a></li>
      </ul>
    </div>
    <div class="ft-col">
      <h4>ADT Sports</h4>
      <ul>
        <?php if(!empty($settings['site_email'])): ?> <li><a href="mailto:<?php echo e($settings['site_email']); ?>">Contact Us</a></li> <?php endif; ?>
        <?php if(!empty($settings['site_phone'])): ?> <li><a href="tel:<?php echo e($settings['site_phone']); ?>"><?php echo e($settings['site_phone']); ?></a></li> <?php endif; ?>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <span>© <?php echo e(date('Y')); ?> <?php echo e($settings['site_name'] ?? 'ADT Sports'); ?>. All rights reserved.</span>
    <span class="footer-tagline">"<?php echo e($settings['footer_tagline'] ?? 'ADT Sports is not covering Kabaddi. It is building its future.'); ?>"</span>
  </div>
</footer>

<script>
let dark = localStorage.getItem('adt-theme')==='dark';
const themeBtn = document.getElementById('themeBtn');
function setTheme(d){dark=d;document.documentElement.setAttribute('data-theme',d?'dark':'light');themeBtn.textContent=d?'☀️':'🌙';localStorage.setItem('adt-theme',d?'dark':'light')}
themeBtn.onclick=()=>setTheme(!dark);
if(dark) setTheme(true);
const hamburger=document.getElementById('hamburger');const mobileNav=document.getElementById('mobileNav');const mobileOverlay=document.getElementById('mobileOverlay');
hamburger.onclick=()=>{mobileNav.classList.toggle('open');mobileOverlay.classList.toggle('open')};
mobileOverlay.onclick=()=>{mobileNav.classList.remove('open');mobileOverlay.classList.remove('open')};
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.getElementById('srchOverlay').classList.remove('open')});
window.addEventListener('scroll',()=>{
  document.getElementById('mainNav').classList.toggle('shadow',window.scrollY>30);
  const d=document.documentElement;document.getElementById('readBar').style.width=((window.scrollY/(d.scrollHeight-d.clientHeight))*100)+'%';
},{passive:true});
</script>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\Users\4yush\OneDrive\Desktop\hehe\adt-sports\resources\views/layouts/frontend.blade.php ENDPATH**/ ?>