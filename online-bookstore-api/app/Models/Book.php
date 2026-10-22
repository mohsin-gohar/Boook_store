<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'title',
        'author',
        'author_id',
        'isbn',
        'description',
        'price',
        'cover_image',
        'images',
        'stock',
        'rating',
        'review_count',
        'is_bestseller',
        'is_new_arrival',
        'language',
        'pages',
        'publisher',
        'release_date',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'rating' => 'float',
        'review_count' => 'integer',
        'is_bestseller' => 'boolean',
        'is_new_arrival' => 'boolean',
        'images' => 'array',
        'release_date' => 'datetime',
    ];

    /**
     * Get the author that owns the book.
     */
    public function author()
    {
        return $this->belongsTo(Author::class);
    }

    /**
     * Get the category that owns the book.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the cart items for the book.
     */
    public function cart()
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * Get the wishlist items for the book.
     */
    public function wishlist()
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Get the order items for the book.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the reviews for the book.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get the average rating for the book.
     *
     * Uses the eager-loaded reviews collection when available to avoid
     * firing an extra query per book (N+1).
     */
    public function getAverageRatingAttribute()
    {
        if ($this->relationLoaded('reviews')) {
            return $this->reviews->isEmpty()
                ? 0
                : round($this->reviews->avg('rating'), 1);
        }

        return round((float) $this->reviews()->avg('rating'), 1);
    }

    /**
     * Get the total number of reviews for the book.
     *
     * Uses the eager-loaded reviews collection when available to avoid
     * firing an extra query per book (N+1).
     */
    public function getReviewsCountAttribute()
    {
        if ($this->relationLoaded('reviews')) {
            return $this->reviews->count();
        }

        return $this->reviews()->count();
    }
}
