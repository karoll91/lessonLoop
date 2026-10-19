<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Deleting the record would also delete graded answers, so the request is
 * refused and the client is told to archive or deactivate instead.
 */
class ResourceInUseException extends RuntimeException
{
    public static function hasGrades(string $what, string $alternative): self
    {
        return new self("{$what} baholar tarixiga bog'langan, o'chirib bo'lmaydi. {$alternative}");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'resource_in_use',
        ], 409);
    }
}
