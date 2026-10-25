<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'capacity',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class)->where('status', Enrollment::STATUS_ENROLLED);
    }

    public function waitlistedEnrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class)
            ->where('status', Enrollment::STATUS_WAITLISTED)
            ->orderBy('waitlisted_at', 'asc')
            ->orderBy('id', 'asc');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function getEnrolledCountAttribute(): int
    {
        if ($this->relationLoaded('activeEnrollments')) {
            return $this->activeEnrollments->count();
        }

        if (isset($this->attributes['active_enrollments_count'])) {
            return (int) $this->attributes['active_enrollments_count'];
        }

        return $this->activeEnrollments()->count();
    }

    public function getWaitlistedCountAttribute(): int
    {
        if ($this->relationLoaded('waitlistedEnrollments')) {
            return $this->waitlistedEnrollments->count();
        }

        if (isset($this->attributes['waitlisted_enrollments_count'])) {
            return (int) $this->attributes['waitlisted_enrollments_count'];
        }

        return $this->waitlistedEnrollments()->count();
    }

    public function getAvailableSeatsAttribute(): int
    {
        return max(0, $this->capacity - $this->enrolled_count);
    }
}
