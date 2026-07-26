<?php
declare(strict_types=1);

final class TaxCalculator
{
    /** Barème IR 2026 sur revenus 2025, estimation hors décote et plafonnement du quotient familial. */
    private const BRACKETS = [
        [11600, 0.00],
        [29579, 0.11],
        [84577, 0.30],
        [181917, 0.41],
        [INF, 0.45],
    ];

    public function incomeTax(float $taxableIncome, float $parts = 1.0): float
    {
        $parts = max(1.0, $parts);
        $perPart = max(0.0, $taxableIncome / $parts);
        $tax = 0.0;
        $lower = 0.0;
        foreach (self::BRACKETS as [$upper, $rate]) {
            $slice = max(0.0, min($perPart, $upper) - $lower);
            $tax += $slice * $rate;
            if ($perPart <= $upper) break;
            $lower = $upper;
        }
        return $tax * $parts;
    }
}
