<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An author of one or more books in the catalogue.
 */
class Author extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'bio',
        'photo_url',
    ];

    /**
     * Get the books written by the author.
     */
    public function books()
    {
        return $this->hasMany(Book::class);
    }
}
