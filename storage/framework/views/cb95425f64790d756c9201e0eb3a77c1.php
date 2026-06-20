<?php $__env->startSection('title', $tag . ' — ' . ($settings['site_name'] ?? 'ADT Sports')); ?>
<?php $__env->startSection('meta_desc', 'Latest ' . $tag . ' news, analysis and stories on ' . ($settings['site_name'] ?? 'ADT Sports')); ?>

<?php $__env->startSection('canonical', $articles->currentPage() > 1 ? $articles->url($articles->currentPage()) : route('tag', $tag)); ?>

<?php $__env->startPush('head_links'); ?>
<?php if($articles->previousPageUrl()): ?><link rel="prev" href="<?php echo e($articles->previousPageUrl()); ?>"><?php endif; ?>
<?php if($articles->nextPageUrl()): ?><link rel="next" href="<?php echo e($articles->nextPageUrl()); ?>"><?php endif; ?>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('schema'); ?>
<script type="application/ld+json">
<?php echo json_encode([
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $tag,  'item' => route('tag', $tag)],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>

</script>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="wrap">

  
  <div style="padding:40px 0 8px;border-bottom:3px solid var(--brand);margin-bottom:32px">
    <div style="display:inline-block;background:var(--brand);color:#fff;font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:3px 12px;border-radius:3px;margin-bottom:12px">
      Tag
    </div>
    <h1 style="font-family:var(--display);font-size:clamp(32px,5vw,52px);font-weight:800;line-height:1.1;color:var(--ink);margin-bottom:10px">
      <?php echo e($tag); ?>

    </h1>
    <div style="font-size:13px;color:var(--ink3);margin-top:10px">
      <?php echo e($articles->total()); ?> <?php echo e(Str::plural('article', $articles->total())); ?>

    </div>
  </div>

  <div class="content-grid">
    <main>
      <?php $__currentLoopData = $articles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <a href="<?php echo e(route('article', $a->slug)); ?>" class="card-row" style="text-decoration:none;display:grid">
        <div>
          <span class="cr-cat" style="<?php echo e($a->category ? 'color:'.$a->category->color : ''); ?>"><?php echo e($a->category?->name ?? 'Article'); ?></span>
          <h2 class="cr-title"><?php echo e($a->title); ?></h2>
          <?php if($a->excerpt): ?><div class="cr-excerpt"><?php echo e($a->excerpt); ?></div><?php endif; ?>
          <div class="cr-meta">
            <span><?php echo e($a->author?->name ?? 'ADT Sports'); ?></span>
            <span class="sep"></span>
            <span><?php echo e($a->formatted_date); ?></span>
            <span class="sep"></span>
            <span><?php echo e($a->read_time); ?> read</span>
          </div>
        </div>
        <div class="cr-thumb" style="background:<?php echo e($a->cover_bg); ?>">
          <?php if($a->cover_image): ?><img src="<?php echo e($a->cover_image); ?>" style="width:100%;height:100%;object-fit:cover" alt="<?php echo e($a->title); ?>" loading="lazy" decoding="async">
          <?php else: ?> <?php echo e($a->cover_emoji); ?> <?php endif; ?>
        </div>
      </a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

      <?php if($articles->hasPages()): ?>
        <div class="pagination-wrap"><?php echo e($articles->links()); ?></div>
      <?php endif; ?>
    </main>

    <aside class="sidebar-col">
      <div class="widget">
        <div class="sec-hd" style="margin-bottom:14px">
          <div class="sec-hd-left"><div class="sec-hd-bar"></div><span class="sec-hd-label">Trending</span></div>
        </div>
        <?php $__currentLoopData = $trending; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(route('article', $t->slug)); ?>" class="card-num" style="text-decoration:none">
          <div class="cn-num">0<?php echo e($i + 1); ?></div>
          <div>
            <div class="cn-title"><?php echo e($t->title); ?></div>
            <div class="cn-meta"><?php echo e($t->category?->name); ?> · <?php echo e($t->formatted_date); ?></div>
          </div>
        </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
      <div class="widget">
        <div class="sec-hd" style="margin-bottom:14px">
          <div class="sec-hd-left"><div class="sec-hd-bar"></div><span class="sec-hd-label">Categories</span></div>
        </div>
        <div class="tag-cloud">
          <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e(route('category', $cat->slug)); ?>" class="tag"><?php echo e($cat->name); ?></a>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>
    </aside>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.frontend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\4yush\OneDrive\Desktop\hehe\adt-sports\resources\views/frontend/tag.blade.php ENDPATH**/ ?>