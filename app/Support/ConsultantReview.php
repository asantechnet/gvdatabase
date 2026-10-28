<?php

namespace App\Support;

/**
 * A counselor's assessment of a visitor or applicant is a fixed three-point
 * choice, not free text. Both resources store the lowercase value and the UI
 * renders its own translated label for it.
 */
final class ConsultantReview
{
    public const GOOD = 'good';

    public const BAD = 'bad';

    public const GREAT = 'great';

    /** @var list<string> */
    public const ALL = [self::GOOD, self::BAD, self::GREAT];
}
