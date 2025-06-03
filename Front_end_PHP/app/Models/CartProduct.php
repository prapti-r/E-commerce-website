<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartProduct extends Model
{
    protected $table = 'CART_PRODUCT';
    
    public $incrementing = false;
    
    // Change from array to string for primary key
    protected $primaryKey = 'cart_id'; // We'll handle the composite key manually
    
    protected $fillable = [
        'cart_id',
        'product_id',
        'product_quantity',
        'total_amount'
    ];

    public $timestamps = false;

    /**
     * Get the primary key value.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->cart_id;
    }

    /**
     * Set the keys for a save update query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        $query->where('cart_id', '=', $this->getAttribute('cart_id'))
              ->where('product_id', '=', $this->getAttribute('product_id'));
        
        return $query;
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id', 'cart_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }
}
