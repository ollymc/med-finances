<?php

declare(strict_types=1);

final class SimulationInput
{
    public function __construct(
        public readonly string $sector,
        public readonly string $structure,
        public readonly int $projectionYears,
        public readonly float $workingWeeks,
        public readonly float $consultationsPerWeek,
        public readonly float $eegPerWeek,
        public readonly float $emgStandardPerWeek,
        public readonly float $emgComplexPerWeek,
        public readonly float $consultationFee,
        public readonly float $eegFee,
        public readonly float $emgStandardFee,
        public readonly float $emgComplexFee,
        public readonly float $expenseRate,
        public readonly float $socialRate,
        public readonly float $incomeTaxRate,
        public readonly float $corporateTaxRate,
        public readonly float $distributionTaxRate,
        public readonly float $salaryShare,
        public readonly float $savingsRate,
        public readonly float $initialCapital,
        public readonly float $annualReturn,
        public readonly float $annualGrowth,
        public readonly float $inflation,
    ) {
    }
}
