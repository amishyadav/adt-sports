<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleRevision extends Model
{
    public $timestamps = false; // immutable snapshot — only created_at is set

    protected $fillable = ['article_id', 'user_id', 'title', 'excerpt', 'body', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
