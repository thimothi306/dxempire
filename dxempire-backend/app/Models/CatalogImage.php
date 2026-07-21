<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogImage extends Model
{
    protected $fillable = ['brand', 'model', 'category', 'image_url'];
}
