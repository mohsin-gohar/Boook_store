<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'rating',
        'comment',
        'likes',
        'liked_by',
    ];

    protected $casts = [
        'rating' => 'integer',
        'likes' => 'integer',
        'liked_by' => 'array',
    ];

    /**
     * Get the user that wrote the review.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the book that was reviewed.
     */
    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}
