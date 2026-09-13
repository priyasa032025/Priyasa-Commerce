<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Models;

use Modules\PriyasaCore\Models\PriyasaModel;

class Product extends PriyasaModel
{
    protected $table = 'priyasa_products';
    protected $guarded = []; protected $casts = ['attributes'=>'array','media'=>'array','published_at'=>'datetime','is_featured'=>'boolean','merchandising_score'=>'decimal:3']; public function variants(){return $this->hasMany(ProductVariant::class);} public function category(){return $this->belongsTo(Category::class);} public function scopePublished($q){return $q->where('status','published')->where(function($q){$q->whereNull('published_at')->orWhere('published_at','<=',now());});}
 public function media(){return $this->hasMany(ProductMedia::class);}
 public function collections(){return $this->belongsToMany(Collection::class,'priyasa_collection_product');}
 public function productAttributes(){return $this->belongsToMany(ProductAttribute::class,'priyasa_product_attributes','product_id','attribute_id')->withPivot('sort_order')->withTimestamps();}
 public function reviews(){return $this->hasMany(Review::class);}
}
