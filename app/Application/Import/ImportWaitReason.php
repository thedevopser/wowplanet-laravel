<?php

declare(strict_types=1);

namespace App\Application\Import;

/**
 * Pourquoi un import n'avance pas.
 *
 * Une attente silencieuse est indistinguable d'un blocage : tout ce qui met l'import
 * en pause plus d'un instant doit tomber dans l'une de ces trois raisons.
 */
enum ImportWaitReason: string
{
    case HourlyBudget = 'hourly_budget';
    case RateLimitBackoff = 'rate_limit_backoff';
    case Batch = 'batch';
}
