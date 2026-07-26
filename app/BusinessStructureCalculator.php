<?php
declare(strict_types=1);

final class BusinessStructureCalculator
{
    public const STRUCTURES = [
        'ei_bnc' => 'Entreprise individuelle — BNC déclaration contrôlée (IR)',
        'micro_bnc' => 'Entreprise individuelle — micro-BNC (IR, sous seuil)',
        'ei_is' => 'Entreprise individuelle avec option IS',
        'scp_ir' => 'SCP — société de personnes à l’IR',
        'selarl_is' => 'SELARL / SELARLU à l’IS — régime TNS',
        'selas_is' => 'SELAS / SELASU à l’IS — modèle assimilé salarié',
    ];

    public function __construct(
        private TaxCalculator $tax,
        private MedicalContributionsCalculator $contributions,
    ) {}

    public function calculate(array $activity, string $sector, string $structure, float $hospitalTaxable, float $parts, array $p): array
    {
        if (!isset(self::STRUCTURES[$structure])) {
            $structure = 'ei_bnc';
        }

        $grossRevenue = max(0.0, (float)$activity['gross_revenue']);
        $optamPrime = max(0.0, (float)($activity['optam_prime'] ?? 0.0));
        $royalty = max(0.0, (float)$activity['hospital_royalty']);
        $expenses = max(0.0, (float)$activity['professional_expenses']);
        $cashBeforeSocial = max(0.0, $grossRevenue + $optamPrime - $royalty - $expenses);
        $otherIncome = max(0.0, (float)$p['household_other_taxable_income']);
        $baselineWithHospital = $this->householdTax($otherIncome + $hospitalTaxable, $parts, $p);

        $result = [
            'structure' => $structure,
            'structure_label' => self::STRUCTURES[$structure],
            'eligible' => true,
            'eligibility_note' => '',
            'gross_revenue' => $grossRevenue,
            'optam_prime' => $optamPrime,
            'professional_revenue_total' => $grossRevenue + $optamPrime,
            'hospital_royalty' => $royalty,
            'professional_expenses' => $expenses,
            'cash_before_social' => $cashBeforeSocial,
            'urssaf' => 0.0,
            'carmf' => 0.0,
            'social_contributions' => 0.0,
            'corporate_tax' => 0.0,
            'personal_professional_tax' => 0.0,
            'dividend_tax' => 0.0,
            'professional_taxable' => 0.0,
            'professional_available' => 0.0,
            'company_retained_earnings' => 0.0,
            'economic_value' => 0.0,
            'remuneration_budget' => 0.0,
            'dividends_gross' => 0.0,
        ];

        if ($structure === 'micro_bnc') {
            $threshold = (float)$p['micro_bnc_threshold'];
            if ($grossRevenue > $threshold) {
                $result['eligible'] = false;
                $result['eligibility_note'] = 'Recettes supérieures au seuil micro-BNC de '.number_format($threshold, 0, ',', ' ').' €.';
            }
            $taxable = $grossRevenue * (1 - (float)$p['micro_bnc_allowance_rate']);
            $social = $this->contributions->calculate($taxable, $sector, $p);
            $professionalTax = max(0.0, $this->householdTax($otherIncome + $hospitalTaxable + $taxable, $parts, $p) - $baselineWithHospital);
            $available = $cashBeforeSocial - $social['total'] - $professionalTax;
            return array_merge($result, [
                'urssaf' => $social['urssaf'],
                'carmf' => $social['carmf'],
                'social_contributions' => $social['total'],
                'professional_taxable' => $taxable,
                'personal_professional_tax' => $professionalTax,
                'professional_available' => $available,
                'economic_value' => $available,
                'contribution_details' => $social,
            ]);
        }

        if (in_array($structure, ['ei_bnc', 'scp_ir'], true)) {
            $social = $this->contributions->calculate($cashBeforeSocial, $sector, $p);
            $taxable = max(0.0, $cashBeforeSocial - $social['total']);
            $professionalTax = max(0.0, $this->householdTax($otherIncome + $hospitalTaxable + $taxable, $parts, $p) - $baselineWithHospital);
            $available = $taxable - $professionalTax;
            return array_merge($result, [
                'urssaf' => $social['urssaf'],
                'carmf' => $social['carmf'],
                'social_contributions' => $social['total'],
                'professional_taxable' => $taxable,
                'personal_professional_tax' => $professionalTax,
                'professional_available' => $available,
                'economic_value' => $available,
                'contribution_details' => $social,
            ]);
        }

        $salaryShare = min(1.0, max(0.0, (float)$p['company_remuneration_share']));
        $distributionShare = min(1.0, max(0.0, (float)$p['company_distribution_share']));
        $remunerationBudget = $cashBeforeSocial * $salaryShare;
        $socialMode = $structure === 'selas_is' ? 'assimilated_salary' : 'pamc';
        $social = $this->contributions->calculate($remunerationBudget, $sector, $p, $socialMode);
        $netRemunerationBeforeTax = max(0.0, $remunerationBudget - $social['total']);
        $companyProfitBeforeTax = max(0.0, $cashBeforeSocial - $remunerationBudget);
        $corporateTax = $this->corporateTax($companyProfitBeforeTax, $p);
        $afterCorporateTax = max(0.0, $companyProfitBeforeTax - $corporateTax);
        $dividendsGross = $afterCorporateTax * $distributionShare;
        $dividendTax = $dividendsGross * (float)$p['dividend_flat_tax_rate'];
        $retained = max(0.0, $afterCorporateTax - $dividendsGross);
        $professionalTax = max(0.0, $this->householdTax($otherIncome + $hospitalTaxable + $netRemunerationBeforeTax, $parts, $p) - $baselineWithHospital);

        $available = max(0.0, $netRemunerationBeforeTax - $professionalTax + $dividendsGross - $dividendTax);
        return array_merge($result, [
            'urssaf' => $social['urssaf'],
            'carmf' => $social['carmf'],
            'social_contributions' => $social['total'],
            'corporate_tax' => $corporateTax,
            'personal_professional_tax' => $professionalTax,
            'dividend_tax' => $dividendTax,
            'professional_taxable' => $netRemunerationBeforeTax,
            'professional_available' => $available,
            'company_retained_earnings' => $retained,
            'economic_value' => $available + $retained,
            'remuneration_budget' => $remunerationBudget,
            'dividends_gross' => $dividendsGross,
            'contribution_details' => $social,
        ]);
    }

    private function householdTax(float $income, float $parts, array $p): float
    {
        return max(0.0,
            $this->tax->incomeTax($income, $parts)
            - (float)$p['annual_tax_reductions']
            - (float)$p['annual_tax_credits']
        );
    }

    private function corporateTax(float $profit, array $p): float
    {
        $reducedLimit = max(0.0, (float)$p['is_reduced_profit_limit']);
        $reduced = min($profit, $reducedLimit) * (float)$p['is_reduced_rate'];
        $standard = max(0.0, $profit - $reducedLimit) * (float)$p['is_standard_rate'];
        return $reduced + $standard;
    }
}
