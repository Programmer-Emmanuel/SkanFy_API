<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Qr extends Model
{
    use HasFactory;

    // ✅ UUID configuration
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'is_active',
        'link_id',
        'image_qr',
        'id_occasion',
        'id_objet',
        'id_user',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->getKey()) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    // ✅ Relations

    public function occasion()
    {
        return $this->belongsTo(Occasion::class, 'id_occasion');
    }

    public function objet()
    {
        return $this->belongsTo(Objet::class, 'id_objet');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

        public function admins()
    {
        return $this->belongsToMany(Admin::class, 'crees', 'qr_id', 'admin_id');
    }

}
