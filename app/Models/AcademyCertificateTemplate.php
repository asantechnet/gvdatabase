<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class AcademyCertificateTemplate extends Model
{
    public const TOKENS = [
        'student_name', 'father_name', 'student_id', 'program', 'class', 'teachers',
        'start_date', 'end_date', 'completion_date', 'certificate_number', 'issue_date',
    ];

    protected $fillable = ['name', 'title', 'body', 'signatory_title', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** Reject unknown tokens when the copy is saved, never at print time. */
    public static function assertTokens(string $body): void
    {
        preg_match_all('/\{([a-z_]+)\}/', $body, $matches);
        $unknown = array_values(array_diff(array_unique($matches[1] ?? []), self::TOKENS));
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'body' => ['business.academy_certificate_token_invalid'],
            ]);
        }
    }

    /** @param array<string, string|null> $values */
    public function renderBody(array $values): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', fn (array $match) => (string) ($values[$match[1]] ?? ''), $this->body) ?? $this->body;
    }
}
