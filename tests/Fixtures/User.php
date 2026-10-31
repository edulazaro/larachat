<?php

namespace EduLazaro\Larachat\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** A host User model with the default $fillable, like a fresh Laravel app. */
class User extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];

    protected $casts = ['last_seen_at' => 'datetime', 'is_available' => 'boolean'];
}
