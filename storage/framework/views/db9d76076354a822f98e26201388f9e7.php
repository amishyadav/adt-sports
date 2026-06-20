
<?php $__env->startSection('title', $article->meta_title ?: $article->title . ' — ' . ($settings['site_name'] ?? 'ADT Sports')); ?>
<?php $__env->startSection('meta_desc', $article->meta_desc ?: $article->excerpt); ?>
<?php $__env->startSection('canonical', route('article', $article->slug)); ?>
<?php $__env->startSection('og_type', 'article'); ?>
<?php if($article->cover_image): ?>
  <?php $__env->startSection('og_image', $article->cover_image); ?>
<?php endif; ?>

<?php if($article->status !== 'published'): ?>
  <?php $__env->startSection('robots', 'noindex, nofollow'); ?>
<?php endif; ?>

<?php $__env->startPush('schema'); ?>
<?php
    $siteName    = $settings['site_name'] ?? 'ADT Sports';
    $articleImg  = $article->cover_image
        ? (\Illuminate\Support\Str::startsWith($article->cover_image, ['http://','https://']) ? $article->cover_image : url($article->cover_image))
        : url('/public/uploads/logo.png');
    $publishedAt = ($article->published_at ?? $article->created_at)?->toAtomString();
    $modifiedAt  = ($article->updated_at ?? $article->published_at ?? $article->created_at)?->toAtomString();

    $blogPosting = [
        '@context'         => 'https://schema.org',
        '@type'            => 'NewsArticle',
        'headline'         => $article->title,
        'description'      => $article->meta_desc ?: $article->excerpt,
        'image'            => $articleImg,
        'datePublished'    => $publishedAt,
        'dateModified'     => $modifiedAt,
        'wordCount'        => str_word_count(strip_tags((string) $article->body)),
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => route('article', $article->slug)],
        'author'           => array_filter([
            '@type' => 'Person',
            'name'  => $article->author?->name ?? $siteName . ' Desk',
            'url'   => $article->author ? route('author', $article->author->id) : null,
        ]),
        'publisher'        => [
            '@type' => 'Organization',
            'name'  => $siteName,
            'logo'  => ['@type' => 'ImageObject', 'url' => url('/public/uploads/logo.png')],
        ],
    ];
    if ($article->category) {
        $blogPosting['articleSection'] = $article->category->name;
    }
    if (is_array($article->tags) && count($article->tags)) {
        $blogPosting['keywords'] = implode(', ', $article->tags);
    }

    $crumbs = [['name' => 'Home', 'item' => url('/')]];
    if ($article->category) {
        $crumbs[] = ['name' => $article->category->name, 'item' => route('category', $article->category->slug)];
    }
    $crumbs[] = ['name' => $article->title, 'item' => route('article', $article->slug)];
    $breadcrumb = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => collect($crumbs)->map(fn ($c, $i) => [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'name'     => $c['name'],
            'item'     => $c['item'],
        ])->values()->all(),
    ];
?>
<script type="application/ld+json">
<?php echo json_encode($blogPosting, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>

</script>
<script type="application/ld+json">
<?php echo json_encode($breadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>

</script>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('og_meta'); ?>
<meta property="article:published_time" content="<?php echo e($publishedAt); ?>">
<meta property="article:modified_time" content="<?php echo e($modifiedAt); ?>">
<?php if($article->author?->name): ?>
<meta property="article:author" content="<?php echo e($article->author->name); ?>">
<?php endif; ?>
<?php if($article->category): ?>
<meta property="article:section" content="<?php echo e($article->category->name); ?>">
<?php endif; ?>
<?php if(is_array($article->tags)): ?>
<?php $__currentLoopData = $article->tags; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<meta property="article:tag" content="<?php echo e($tag); ?>">
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php endif; ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="article-wrap">

  
  <article class="article-main">
    <a href="<?php echo e(route('home')); ?>" class="back-btn">← Back to Home</a>

    <div class="art-hero-img" style="background:<?php echo e($article->cover_bg); ?>">
      <?php if($article->cover_image): ?>
        <img src="<?php echo e($article->cover_image); ?>" alt="<?php echo e($article->title); ?>" fetchpriority="high" decoding="async">
      <?php else: ?>
        <span style="position:relative;z-index:1"><?php echo e($article->cover_emoji); ?></span>
      <?php endif; ?>
    </div>

    <?php if($article->category): ?>
      <a href="<?php echo e(route('category', $article->category->slug)); ?>" class="art-cat"
         style="background:<?php echo e($article->category->color); ?>">
        <?php echo e($article->category->name); ?>

      </a>
    <?php endif; ?>

    <h1 class="art-title"><?php echo e($article->title); ?></h1>

    <?php if($article->excerpt): ?>
      <p class="art-deck"><?php echo e($article->excerpt); ?></p>
    <?php endif; ?>

    <div class="art-byline">
      <div class="byline-av">✍️</div>
      <div>
        <div class="byline-name">
        <?php if($article->author): ?>
          <a href="<?php echo e(route('author', $article->author->id)); ?>" style="color:inherit" rel="author"><?php echo e($article->author->name); ?></a>
        <?php else: ?>
          ADT Sports Desk
        <?php endif; ?>
      </div>
        <div class="byline-info"><?php echo e($article->formatted_date); ?> · <?php echo e($article->read_time); ?> read · <?php echo e(number_format($article->views)); ?> views</div>
      </div>
      <div class="byline-actions">
        <button class="action-btn" onclick="shareArticle()" title="Share">📤</button>
        <button class="action-btn" onclick="cycleFontSize()" title="Adjust font size">Aa</button>
      </div>
    </div>

    
    <?php if($article->tags && count($article->tags)): ?>
    <div style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:28px">
      <?php $__currentLoopData = $article->tags; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(route('tag', $tag)); ?>" class="tag" style="font-size:11px"><?php echo e($tag); ?></a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <?php endif; ?>

    
    <div class="art-body" id="artBody">
      <?php echo $article->body; ?>

    </div>

    
    <?php if($article->author): ?>
    <div class="widget" style="margin-top:36px;display:flex;gap:14px;align-items:flex-start">
      <div class="byline-av" style="width:46px;height:46px;font-size:18px">✍️</div>
      <div>
        <div style="font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--ink3);margin-bottom:3px">Written by</div>
        <a href="<?php echo e(route('author', $article->author->id)); ?>" rel="author" style="font-family:var(--display);font-size:17px;font-weight:700;color:var(--ink)"><?php echo e($article->author->name); ?></a>
        <?php if($article->author->bio): ?>
          <p style="font-size:13.5px;line-height:1.6;color:var(--ink2);margin-top:6px"><?php echo e($article->author->bio); ?></p>
        <?php endif; ?>
        <a href="<?php echo e(route('author', $article->author->id)); ?>" style="font-size:12.5px;font-weight:600;color:var(--brand);margin-top:8px;display:inline-block">More from <?php echo e($article->author->name); ?> →</a>
      </div>
    </div>
    <?php endif; ?>

    
    <?php if($related->count()): ?>
    <div class="related-section">
      <div class="sec-hd">
        <div class="sec-hd-left">
          <div class="sec-hd-bar"></div>
          <span class="sec-hd-label">More Stories</span>
        </div>
      </div>
      <div class="cards-grid" style="margin-top:18px">
        <?php $__currentLoopData = $related; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(route('article', $r->slug)); ?>" class="card-box" style="text-decoration:none">
          <div class="cb-thumb" style="background:<?php echo e($r->cover_bg); ?>">
            <?php if($r->cover_image): ?>
              <img src="<?php echo e($r->cover_image); ?>" style="width:100%;height:100%;object-fit:cover" alt="<?php echo e($r->title); ?>" loading="lazy" decoding="async">
            <?php else: ?>
              <?php echo e($r->cover_emoji); ?>

            <?php endif; ?>
          </div>
          <?php if($r->category): ?>
            <span class="cb-cat" style="color:<?php echo e($r->category->color); ?>"><?php echo e($r->category->name); ?></span>
          <?php endif; ?>
          <h2 class="cb-title"><?php echo e($r->title); ?></h2>
          <div class="cb-meta"><?php echo e($r->formatted_date); ?></div>
        </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
    <?php endif; ?>
  </article>

  
  <aside class="art-sidebar">
    <div class="art-sidebar-sticky">

      
      <div class="widget widget-nl" style="margin-bottom:22px">
        <div class="sec-hd" style="border-bottom-color:rgba(255,255,255,.1);margin-bottom:12px">
          <div class="sec-hd-left">
            <div class="sec-hd-bar"></div>
            <span class="sec-hd-label" style="color:#F0EBE5">Daily Digest</span>
          </div>
        </div>
        <p class="nl-desc" style="font-size:13px">Top Kabaddi stories straight to your inbox — free.</p>
        <input type="email" class="nl-input" placeholder="your@email.com" id="sideNlEmail">
        <button class="nl-btn" onclick="subscribeSide()">Subscribe →</button>
      </div>

      
      <?php if($trending->count()): ?>
      <div class="widget">
        <div class="sec-hd" style="margin-bottom:14px">
          <div class="sec-hd-left">
            <div class="sec-hd-bar"></div>
            <span class="sec-hd-label">More Stories</span>
          </div>
        </div>
        <?php $__currentLoopData = $trending->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(route('article', $t->slug)); ?>" class="card-num" style="text-decoration:none">
          <div style="width:52px;height:52px;border-radius:6px;background:<?php echo e($t->cover_bg); ?>;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;overflow:hidden">
            <?php if($t->cover_image): ?>
              <img src="<?php echo e($t->cover_image); ?>" style="width:100%;height:100%;object-fit:cover" alt="<?php echo e($t->title); ?>" loading="lazy" decoding="async">
            <?php else: ?>
              <?php echo e($t->cover_emoji); ?>

            <?php endif; ?>
          </div>
          <div>
            <div class="cn-title"><?php echo e($t->title); ?></div>
            <div class="cn-meta"><?php echo e($t->formatted_date); ?></div>
          </div>
        </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
      <?php endif; ?>

    </div>
  </aside>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
const fontSizes = ['16px','18px','20px'];
let fsIdx = 1;
function cycleFontSize() {
  fsIdx = (fsIdx + 1) % fontSizes.length;
  document.getElementById('artBody').style.fontSize = fontSizes[fsIdx];
}
function shareArticle() {
  if (navigator.share) {
    navigator.share({ title: '<?php echo e(addslashes($article->title)); ?>', url: window.location.href });
  } else {
    navigator.clipboard.writeText(window.location.href).then(() => alert('Link copied to clipboard!'));
  }
}
function subscribeSide() {
  const e = document.getElementById('sideNlEmail').value;
  if (!e || !e.includes('@')) { alert('Please enter a valid email address.'); return; }
  alert('✅ Subscribed! You\'ll receive the Daily Digest soon.');
  document.getElementById('sideNlEmail').value = '';
}
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.frontend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\4yush\OneDrive\Desktop\hehe\adt-sports\resources\views/frontend/article.blade.php ENDPATH**/ ?>