<?php

namespace App\Services\Recommend;

use App\Models\User;

/**
 * Stub de recomanació Gemini per al feed «Triats per a tu» (T046).
 * En producció substituir per crida Google AI SDK; aquí només reordena IDs candidats.
 */
class GeminiHomeRecommendService
{
    /**
     * @param  array<int>  $candidateEventIds
     * @return array<int>
     */
    public function rankEventIds (array $candidateEventIds, User $user): array
    {
        $ids = array_values(array_unique($candidateEventIds));
        sort($ids);
        // Deterministic pseudo-shuffle per user id (evita aleatori en tests)
        $salt = (int) $user->id;
        usort($ids, fn (int $a, int $b): int => ($a ^ $salt) <=> ($b ^ $salt));

        return array_slice($ids, 0, min(12, count($ids)));
    }
}
