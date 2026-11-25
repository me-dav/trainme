<?php

namespace App\Models;

use App\Enums\AiImportStatus;
use Database\Factories\AiImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A PDF/Word document that AI turns into a module file. Once `ready`, the generated file
 * goes through the same preview → confirm flow as a manual upload.
 */
#[Fillable([
    'user_id', 'course_id', 'original_name', 'source_path', 'status', 'attempts', 'progress', 'result', 'error',
    'validation_errors', 'prompt_tokens', 'completion_tokens', 'started_at', 'finished_at',
])]
class AiImport extends Model
{
    /** @use HasFactory<AiImportFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AiImportStatus::class,
            'attempts' => 'integer',
            'validation_errors' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
