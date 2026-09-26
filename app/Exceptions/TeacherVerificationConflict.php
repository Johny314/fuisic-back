<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Действие с заявкой невозможно в её текущем статусе. В API — 409 с текстом,
 * в админке ловится и показывается уведомлением.
 */
class TeacherVerificationConflict extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
