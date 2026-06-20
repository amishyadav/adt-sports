
<?php $__env->startSection('title', ($settings['site_name'] ?? 'ADT Sports')); ?>

<?php if($catSlug && $categories->firstWhere('slug', $catSlug)): ?>
  <?php $__env->startSection('canonical', route('category', $catSlug)); ?>
<?php else: ?>
  
  <?php $__env->startSection('canonical', $articles->currentPage() > 1 ? $articles->url($articles->currentPage()) : route('home')); ?>
  <?php $__env->startPush('head_links'); ?>
    <?php if($articles->previousPageUrl()): ?><link rel="prev" href="<?php echo e($articles->previousPageUrl()); ?>"><?php endif; ?>
    <?php if($articles->nextPageUrl()): ?><link rel="next" href="<?php echo e($articles->nextPageUrl()); ?>"><?php endif; ?>
  <?php $__env->stopPush(); ?>
<?php endif; ?>

<?php $__env->startSection('content'); ?>
<div class="wrap">

  <h1 class="sr-only"><?php echo e($settings['site_name'] ?? 'ADT Sports'); ?> — <?php echo e($settings['site_tagline'] ?? "India's #1 Kabaddi Media Platform"); ?></h1>

  
  <?php if($heroLead): ?>
  <div class="home-hero">
    <a href="<?php echo e(route('article', $heroLead->slug)); ?>" class="hero-lead">
      <div class="hero-lead-art" style="background:<?php echo e($heroLead->cover_bg); ?>">
        <?php if($heroLead->cover_image): ?>
          <img src="<?php echo e($heroLead->cover_image); ?>" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover" alt="<?php echo e($heroLead->title); ?>" fetchpriority="high" decoding="async">
        <?php else: ?>
          <?php echo e($heroLead->cover_emoji); ?>

        <?php endif; ?>
      </div>
      <div class="hero-lead-veil"></div>
      <div class="hero-lead-body">
        <?php if($heroLead->category): ?>
          <span class="cat-pill"><?php echo e($heroLead->category->name); ?></span>
        <?php endif; ?>
        <h2 class="hero-lead-title"><?php echo e($heroLead->title); ?></h2>
        <div class="hero-lead-meta">
          <span><?php echo e($heroLead->author?->name ?? 'ADT Sports'); ?></span>
          <span class="sep"></span>
          <span><?php echo e($heroLead->formatted_date); ?></span>
          <span class="sep"></span>
          <span><?php echo e($heroLead->read_time); ?> read</span>
        </div>
      </div>
    </a>

    <div class="hero-stack">
      <?php $__currentLoopData = $heroStack; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <a href="<?php echo e(route('article', $a->slug)); ?>" class="hero-stack-item">
        <div class="stack-thumb" style="background:<?php echo e($a->cover_bg); ?>">
          <?php if($a->cover_image): ?>
            <img src="<?php echo e($a->cover_image); ?>" style="width:100%;height:100%;object-fit:cover" alt="<?php echo e($a->title); ?>" loading="lazy" decoding="async">
          <?php else: ?>
            <?php echo e($a->cover_emoji); ?>

          <?php endif; ?>
        </div>
        <div>
          <?php if($a->category): ?><div class="stack-cat"><?php echo e($a->category->name); ?></div><?php endif; ?>
          <h3 class="stack-title"><?php echo e($a->title); ?></h3>
          <div class="stack-meta"><?php echo e($a->formatted_date); ?> · <?php echo e($a->read_time); ?> read</div>
        </div>
      </a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>
  <?php endif; ?>

  
  <div class="cat-tabs">
    <a href="<?php echo e(route('home')); ?>" class="ctab <?php echo e(!$catSlug ? 'active' : ''); ?>">All</a>
    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <a href="<?php echo e(route('home', ['category' => $cat->slug])); ?>"
         class="ctab <?php echo e($catSlug === $cat->slug ? 'active' : ''); ?>">
        <?php echo e($cat->name); ?>

      </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>

  
  <div class="content-grid">
    <main>
      <div class="sec-hd">
        <div class="sec-hd-left">
          <div class="sec-hd-bar"></div>
          <span class="sec-hd-label">
            <?php echo e($catSlug ? ($categories->firstWhere('slug',$catSlug)?->name ?? 'Articles') : 'Latest Stories'); ?>

          </span>
        </div>
        <a href="<?php echo e(route('search')); ?>" class="sec-hd-more">All Articles →</a>
      </div>

      
      <?php $__empty_1 = true; $__currentLoopData = $articles->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <a href="<?php echo e(route('article', $a->slug)); ?>" class="card-row" style="text-decoration:none;display:grid">
        <div>
          <span class="cr-cat" style="<?php echo e($a->category ? 'color:'.$a->category->color : ''); ?>">
            <?php echo e($a->category?->name ?? 'Article'); ?>

          </span>
          <h2 class="cr-title"><?php echo e($a->title); ?></h2>
          <?php if($a->excerpt): ?>
            <div class="cr-excerpt"><?php echo e($a->excerpt); ?></div>
          <?php endif; ?>
          <div class="cr-meta">
            <span><?php echo e($a->author?->name ?? 'ADT Sports'); ?></span>
            <span class="sep"></span>
            <span><?php echo e($a->formatted_date); ?></span>
            <span class="sep"></span>
            <span><?php echo e($a->read_time); ?> read</span>
          </div>
        </div>
        <div class="cr-thumb" style="background:<?php echo e($a->cover_bg); ?>">
          <?php if($a->cover_image): ?>
            <img src="<?php echo e($a->cover_image); ?>" style="width:100%;height:100%;object-fit:cover" alt="<?php echo e($a->title); ?>" loading="lazy" decoding="async">
          <?php else: ?>
            <?php echo e($a->cover_emoji); ?>

          <?php endif; ?>
        </div>
      </a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div style="text-align:center;padding:64px 20px;color:var(--ink3)">
        <div style="font-size:44px;margin-bottom:14px">📭</div>
        <p style="font-size:15px">No articles found<?php echo e($catSlug ? ' in this category' : ''); ?>.</p>
        <?php if($catSlug): ?>
          <a href="<?php echo e(route('home')); ?>" style="color:var(--brand);font-size:14px;margin-top:10px;display:inline-block">← Back to all articles</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      
      <?php if($articles->count() > 5): ?>
      <div class="feature-strip">
        <?php $__currentLoopData = $articles->slice(5, 3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(route('article', $a->slug)); ?>" class="fs-item" style="text-decoration:none">
          <div class="fs-cat"><?php echo e($a->breaking ? '🔴 Breaking' : ($a->category?->name ?? 'Article')); ?></div>
          <h3 class="fs-title"><?php echo e($a->title); ?></h3>
          <div class="fs-meta"><?php echo e($a->read_time); ?> read · <?php echo e($a->formatted_date); ?></div>
        </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
      <?php endif; ?>

      
      <?php if($articles->count() > 8): ?>
      <div class="sec-hd">
        <div class="sec-hd-left">
          <div class="sec-hd-bar"></div>
          <span class="sec-hd-label">Must Read</span>
        </div>
      </div>
      <div class="cards-grid">
        <?php $__currentLoopData = $articles->slice(8, 3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(route('article', $a->slug)); ?>" class="card-box" style="text-decoration:none">
          <div class="cb-thumb" style="background:<?php echo e($a->cover_bg); ?>">
            <?php if($a->cover_image): ?>
              <img src="<?php echo e($a->cover_image); ?>" style="width:100%;height:100%;object-fit:cover" alt="<?php echo e($a->title); ?>" loading="lazy" decoding="async">
            <?php else: ?>
              <?php echo e($a->cover_emoji); ?>

            <?php endif; ?>
          </div>
          <span class="cb-cat" style="<?php echo e($a->category ? 'color:'.$a->category->color : ''); ?>">
            <?php echo e($a->category?->name ?? ''); ?>

          </span>
          <h3 class="cb-title"><?php echo e($a->title); ?></h3>
          <?php if($a->excerpt): ?><div class="cb-excerpt"><?php echo e($a->excerpt); ?></div><?php endif; ?>
          <div class="cb-meta"><?php echo e($a->formatted_date); ?> · <?php echo e($a->read_time); ?> read</div>
        </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
      <?php endif; ?>

      
      <?php if($articles->hasPages()): ?>
      <div class="pagination-wrap">
        <?php echo e($articles->links()); ?>

      </div>
      <?php endif; ?>

    </main>

    
    <aside class="sidebar-col">

      
      <div class="widget">
        <div style="display:inline-flex;align-items:center;gap:6px;background:var(--brand-soft);color:var(--brand);font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:3px 10px;border-radius:20px;margin-bottom:14px">
          🔥 Trending Now
        </div>
        <?php $__empty_1 = true; $__currentLoopData = $trending; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <a href="<?php echo e(route('article', $a->slug)); ?>" class="card-num" style="text-decoration:none">
          <div class="cn-num">0<?php echo e($i + 1); ?></div>
          <div>
            <div class="cn-title"><?php echo e($a->title); ?></div>
            <div class="cn-meta"><?php echo e($a->category?->name ?? ''); ?> · <?php echo e($a->formatted_date); ?></div>
          </div>
        </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <p style="color:var(--ink3);font-size:13px">No trending articles yet.</p>
        <?php endif; ?>
      </div>

      
      <div class="widget widget-nl" id="newsletter">
        <div class="sec-hd" style="border-bottom-color:rgba(255,255,255,.1);margin-bottom:14px">
          <div class="sec-hd-left">
            <div class="sec-hd-bar"></div>
            <span class="sec-hd-label" style="color:#F0EBE5">Daily Digest</span>
          </div>
        </div>
        <p class="nl-desc">Get the biggest Kabaddi headlines, match analysis, and exclusive stories delivered straight to your inbox.</p>
        <input type="email" class="nl-input" placeholder="your@email.com" id="nlEmail">
        <button class="nl-btn" onclick="subscribeNl()">Subscribe Now →</button>
      </div>

      
      <div class="widget">
        <div class="sec-hd" style="margin-bottom:14px">
          <div class="sec-hd-left">
            <div class="sec-hd-bar"></div>
            <span class="sec-hd-label">Topics</span>
          </div>
        </div>
        <div class="tag-cloud">
          <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e(route('category', $cat->slug)); ?>" class="tag"><?php echo e($cat->name); ?></a>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>

      
      <div class="widget">
        <div class="about-mini-logo">
          <div class="am-img"><img src="/public/uploads/logo.png" onerror="this.style.display='none'" alt="ADT"></div>
          <div class="am-name"><span>ADT</span> Sports</div>
        </div>
        <p class="about-mini-desc">India's #1 Kabaddi media platform — covering every raid, every story, every league.</p>
        <div class="socials-row">
          <?php if(!empty($settings['facebook_url'])): ?>  <a href="<?php echo e($settings['facebook_url']); ?>"  target="_blank" class="soc-btn"><i class="fa-brands fa-facebook"></i> Follow</a> <?php endif; ?>
          <?php if(!empty($settings['instagram_url'])): ?> <a href="<?php echo e($settings['instagram_url']); ?>" target="_blank" class="soc-btn"><i class="fa-brands fa-instagram"></i> Follow</a> <?php endif; ?>
          <?php if(!empty($settings['youtube_url'])): ?>   <a href="<?php echo e($settings['youtube_url']); ?>"   target="_blank" class="soc-btn"><i class="fa-brands fa-youtube"></i> Watch</a>  <?php endif; ?>
          <?php if(empty($settings['facebook_url']) && empty($settings['instagram_url']) && empty($settings['youtube_url'])): ?>
            <span class="soc-btn"><i class="fa-brands fa-facebook"></i> Follow</span>
            <span class="soc-btn"><i class="fa-brands fa-instagram"></i> Follow</span>
            <span class="soc-btn"><i class="fa-brands fa-youtube"></i> Watch</span>
          <?php endif; ?>
        </div>
      </div>

    </aside>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
function subscribeNl() {
  const e = document.getElementById('nlEmail').value;
  if (!e || !e.includes('@')) { alert('Please enter a valid email address.'); return; }
  alert('✅ Thanks for subscribing! You\'ll receive the Daily Digest soon.');
  document.getElementById('nlEmail').value = '';
}
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.frontend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\4yush\OneDrive\Desktop\hehe\adt-sports\resources\views/frontend/home.blade.php ENDPATH**/ ?>