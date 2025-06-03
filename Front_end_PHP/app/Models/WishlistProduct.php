<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WishlistProduct extends Model
{
    protected $table = 'WISHLIST_PRODUCT';
    protected $primaryKey = ['wishlist_id', 'product_id'];
    public $incrementing = false;
    protected $fillable = ['wishlist_id', 'product_id', 'product_quantity'];
    public $timestamps = false;
}