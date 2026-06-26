<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Mews\Purifier\Facades\Purifier;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = ['article_id', 'author_name', 'author_email', 'body', 'approved', 'ip'];

    protected $casts = ['approved' => 'boolean'];

    /**
     * Sanitize the comment body on write with a deliberately tiny allowlist
     * (basic formatting + links only). Same stored-XSS chokepoint pattern as
     * Article::setBodyAttribute, so {!! $comment->body !!} is safe to render.
     */
    public function setBodyAttribute(?string $value): void
    {
        $this->attributes['body'] = $value === null || $value === ''
            ? $value
            : Purifier::clean($value, 'comment');
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('approved', true);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('approved', false);
    }
}
