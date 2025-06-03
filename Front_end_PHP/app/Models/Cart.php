<?php
// app/Models/Cart.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $table      = 'CART';
    protected $primaryKey = 'cart_id';
    public    $incrementing = false;
    protected $keyType    = 'string';
    public    $timestamps = false;

    protected $fillable = ['user_id','creation_date'];

    public function user()   { return $this->belongsTo(User::class,'user_id'); }
    public function products(){ return $this->belongsToMany(
        Product::class,'CART_PRODUCT','cart_id','product_id')
        ->withPivot(['product_quantity','total_amount']); }

    public function order()  { return $this->hasOne(Order::class,'cart_id'); }
}
