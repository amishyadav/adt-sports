<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $fillable = ['filename','original_name','alt','mimetype','size','url','disk','uploaded_by'];

    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function getFormattedSizeAttribute(): string
    {
        $b = $this->size;
        if ($b < 1024)    return $b . ' B';
        if ($b < 1048576) return round($b/1024, 1) . ' KB';
        return round($b/1048576, 1) . ' MB';
    }
}
