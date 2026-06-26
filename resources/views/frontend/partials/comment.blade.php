<div class="comment" style="padding:14px 0;border-bottom:1px solid var(--line,#2a2118)">
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
    <strong style="font-size:14px;color:var(--ink)">{{ $comment->author_name }}</strong>
    <span style="font-size:11px;color:var(--ink3)">{{ $comment->created_at->format('d M Y') }}</span>
  </div>
  <div class="comment-body" style="font-size:14px;line-height:1.6;color:var(--ink2)">{!! $comment->body !!}</div>
</div>
