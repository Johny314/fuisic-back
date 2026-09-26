<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Изменения записей в админке — в журнал действий от имени вошедшего (последний в backpack.base.middleware_class).
 */
class RecordAdminActivity
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = backpack_user();

        return $user instanceof User
            ? $this->audit->asStaff($user, fn () => $next($request))
            : $next($request);
    }
}
