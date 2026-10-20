<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Scrive una riga di audit log in modo idempotente.
 *
 * La chiave è (action, subject_type, subject_id): una seconda registrazione
 * della stessa azione sullo stesso soggetto aggiorna la riga esistente invece
 * di crearne una duplicata. Questo evita doppi record quando la stessa
 * operazione viene eseguita attraverso due percorsi diversi (per esempio la
 * sospensione di un evento, che puo' passare dal CancelEvent).
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(string $action, Model|string $subject, array $metadata = [], ?int $actorUserId = null): AuditLog
    {
        $subjectType = $subject instanceof Model ? $subject::class : $subject;
        $subjectId = $subject instanceof Model ? $subject->getKey() : null;

        return AuditLog::query()->updateOrCreate(
            [
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
            ],
            [
                'actor_user_id' => $actorUserId ?? request()->user()?->getAuthIdentifier(),
                'metadata' => $metadata,
                'request_id' => request()->attributes->get('request_id'),
            ],
        );
    }
}
