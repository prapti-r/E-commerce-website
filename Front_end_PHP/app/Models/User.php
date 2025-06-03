<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;

    protected $table = 'USER1'; // Specify the existing table name

    protected $primaryKey = 'user_id'; // Use user_id as the primary key
    public $incrementing = false; // Disable auto-increment since user_id is VARCHAR
    protected $keyType = 'string'; // Set key type to string
        public $timestamps = true; // Enable timestamps

    // Specify Oracle uppercase column names for Laravel's expectations
    const CREATED_AT = 'CREATED_AT';
    const UPDATED_AT = 'UPDATED_AT';



    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'user_type',
        'email',
        'contact_no',
        'password',
        'otp',
        'is_verified',
        'otp_expires_at',
        'user_image',
        'USER_IMAGE_MIMETYPE',
        'USER_IMAGE_FILENAME',
        'USER_IMAGE_LASTUPD'
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'otp_expires_at' => 'datetime',
        'contact_no' => 'integer',
         'USER_IMAGE_LASTUPD' => 'datetime',
    ];

    protected $hidden = [
        'password',
        'otp',
    ];

    // Generate a unique user_id (e.g., 8-character random string)
    public static function generateUserId()
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        do {
            $userId = '';
            for ($i = 0; $i < 8; $i++) {
                $userId .= $characters[rand(0, strlen($characters) - 1)];
            }
        } while (self::where('user_id', $userId)->exists()); // Ensure uniqueness
        return $userId;
    }
}
