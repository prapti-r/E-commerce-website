<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    // 1) Tell Eloquent which table + primary key
    protected $table = 'PRODUCT';
    protected $primaryKey = 'product_id';

    // 2) Disable auto-increment & timestamps
    public $incrementing = false;
    public $keyType = 'string';
    public $timestamps = false;

    // 3) Allow mass-assignment on these columns
    protected $fillable = [
        'product_id',
        'product_name',
        'stock',
        'shop_id',
        'category_id',
        'description',
        'unit_price',
        'discount_id',
        'price_after_discount',
        'PRODUCT_image',
        'PRODUCT_IMAGE_MIMETYPE',
        'PRODUCT_IMAGE_FILENAME',
        'PRODUCT_IMAGE_LASTUPD',
    ];

    // (Optional) If you want to cast prices to floats:
    protected $casts = [
        'unit_price'           => 'float',
        'price_after_discount' => 'float',
    ];

    /**
     * Get the category that owns the product.
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'category_id');
    }
    
    /**
     * Get the shop that owns the product.
     */
    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'shop_id');
    }
    
    /**
     * Get image attribute with proper handling
     */
    public function getImageAttribute($value)
    {
        // Return the raw image data if it exists
        if (!is_null($value) && !empty($value)) {
            return $value;
        }
        
        return null;
    }
    
    /**
     * Check if the product has an image
     */
    public function hasImage()
    {
        return !is_null($this->PRODUCT_image) && !empty($this->PRODUCT_image);
    }
}
