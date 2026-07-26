<?php
declare(strict_types=1);

final class FinancialSimulator
{
    private BusinessStructureCalculator $structures;

    public function __construct(private TaxCalculator $tax)
    {
        $this->structures = new BusinessStructureCalculator($tax, new MedicalContributionsCalculator());
    }

    public function simulate(array $p): array
    {
        $years = max(2, min(40, (int)$p['horizon_years']));
        $rows = [];
        $wealth1 = (float)$p['initial_assets'] - (float)$p['initial_debt'];
        $wealth2 = $wealth1;
        $cumulative1 = 0.0;
        $cumulative2 = 0.0;
        $crossing = null;
        $hospitalBase = $this->hospitalCompensation($p);
        $parts = $this->taxParts($p);
        $otherIncome = max(0.0, (float)$p['household_other_taxable_income']);
        $taxWithoutHospital = $this->householdTax($otherIncome, $parts, $p);

        for ($year = 1; $year <= $years; $year++) {
            $growth = pow(1 + (float)$p['annual_growth'], $year - 1);
            $hospital = $this->scaleHospital($hospitalBase, $growth);
            $hospitalTax = max(0.0, $this->householdTax($otherIncome + $hospital['net'], $parts, $p) - $taxWithoutHospital);
            $hospitalAfterTax = max(0.0, $hospital['net'] - $hospitalTax);

            $ramp = $year === 1 ? (float)$p['activity_ramp_year1'] : ($year === 2 ? (float)$p['activity_ramp_year2'] : 1.0);
            $s1Activity = $this->professionalYear($p, 's1', $growth, $ramp);
            $s1Professional = $this->structures->calculate($s1Activity, 's1', (string)$p['structure_s1'], $hospital['net'], $parts, $p);
            $s1 = $this->mergeScenario($s1Activity, $s1Professional, $hospital, $hospitalTax, $hospitalAfterTax);

            if ($year === 1) {
                $s2Activity = $this->emptyActivity();
                $s2Professional = $this->structures->calculate($s2Activity, 's2', (string)$p['structure_s2'], $hospital['net'], $parts, $p);
                $s2 = $this->mergeScenario($s2Activity, $s2Professional, $hospital, $hospitalTax, $hospitalAfterTax);
            } else {
                $s2Ramp = $year === 2 ? (float)$p['activity_ramp_year1'] : ($year === 3 ? (float)$p['activity_ramp_year2'] : 1.0);
                $s2Activity = $this->professionalYear($p, 's2', $growth, $s2Ramp);
                $s2Professional = $this->structures->calculate($s2Activity, 's2', (string)$p['structure_s2'], $hospital['net'], $parts, $p);
                $s2 = $this->mergeScenario($s2Activity, $s2Professional, $hospital, $hospitalTax, $hospitalAfterTax);
            }

            $save1 = max(0.0, $s1['professional_available'] + $s1['hospital_after_tax']) * (float)$p['savings_rate']
                + (float)$p['annual_real_estate_saving'] + $s1['company_retained_earnings'];
            $save2 = max(0.0, $s2['professional_available'] + $s2['hospital_after_tax']) * (float)$p['savings_rate']
                + (float)$p['annual_real_estate_saving'] + $s2['company_retained_earnings'];
            $wealth1 = $wealth1 * (1 + (float)$p['investment_return']) + $save1;
            $wealth2 = $wealth2 * (1 + (float)$p['investment_return']) + $save2;
            $cumulative1 += $s1['available_income'];
            $cumulative2 += $s2['available_income'];
            if ($crossing === null && $wealth2 >= $wealth1 && $year > 1) {
                $crossing = $year;
            }
            $rows[] = [
                'year' => $year,
                's1' => $s1,
                's2' => $s2,
                'saving_s1' => $save1,
                'saving_s2' => $save2,
                'wealth_s1' => $wealth1,
                'wealth_s2' => $wealth2,
                'real_wealth_s1' => $wealth1 / pow(1 + (float)$p['inflation'], $year),
                'real_wealth_s2' => $wealth2 / pow(1 + (float)$p['inflation'], $year),
                'cumulative_available_s1' => $cumulative1,
                'cumulative_available_s2' => $cumulative2,
            ];
        }

        $first = $rows[0];
        $last = end($rows);
        $steadyS1Activity = $this->professionalYear($p, 's1', 1.0, 1.0);
        $steadyS2Activity = $this->professionalYear($p, 's2', 1.0, 1.0);

        return [
            'rows' => $rows,
            'crossing_year' => $crossing,
            'waiting_cost_year1' => $first['s1']['available_income'] - $first['s2']['available_income'],
            'final_wealth_difference' => $last['wealth_s2'] - $last['wealth_s1'],
            'final_income_difference' => $last['cumulative_available_s2'] - $last['cumulative_available_s1'],
            'acts' => $this->actComparison($p),
            'hospital' => $hospitalBase,
            'tax_parts' => $parts,
            'structures' => BusinessStructureCalculator::STRUCTURES,
            'structure_comparison_s1' => $this->compareStructures($steadyS1Activity, 's1', $hospitalBase['net'], $parts, $p),
            'structure_comparison_s2' => $this->compareStructures($steadyS2Activity, 's2', $hospitalBase['net'], $parts, $p),
        ];
    }

    private function compareStructures(array $activity, string $sector, float $hospitalTaxable, float $parts, array $p): array
    {
        $out = [];
        foreach (BusinessStructureCalculator::STRUCTURES as $key => $label) {
            $out[] = $this->structures->calculate($activity, $sector, $key, $hospitalTaxable, $parts, $p);
        }
        usort($out, fn(array $a, array $b): int => $b['economic_value'] <=> $a['economic_value']);
        return $out;
    }

    private function hospitalCompensation(array $p): array
    {
        $step = $p['hospital_step_mode'] === 'manual'
            ? max(1, min(13, (int)$p['hospital_step_manual']))
            : $this->stepFromSeniority((float)$p['hospital_seniority_years']);
        $grossBase = (float)($p['hospital_step_amounts'][$step] ?? $p['hospital_step_amounts'][1]);
        $n = max(1, (int)$p['hospital_practitioner_count']);
        $onCallWeekday = (float)$p['oncall_weekday_periods_service'] / $n * (float)$p['oncall_weekday_rate'];
        $onCallWeekend = (float)$p['oncall_weekend_periods_service'] / $n * (float)$p['oncall_weekend_rate'];
        $onCallHoliday = (float)$p['oncall_holiday_periods_service'] / $n * (float)$p['oncall_holiday_rate'];
        $onCallGross = $onCallWeekday + $onCallWeekend + $onCallHoliday + (float)$p['oncall_other_gross'];
        $gross = $grossBase + $onCallGross + (float)$p['hospital_annual_indemnities_gross'];
        $employeeRate = min(0.60, max(0.0,
            (float)$p['hospital_pension_rate'] + (float)$p['hospital_csg_crds_rate'] + (float)$p['hospital_other_employee_rate']
        ));
        return [
            'step' => $step,
            'base_gross' => $grossBase,
            'oncall_weekday_gross' => $onCallWeekday,
            'oncall_weekend_gross' => $onCallWeekend,
            'oncall_holiday_gross' => $onCallHoliday,
            'oncall_gross' => $onCallGross,
            'total_gross' => $gross,
            'employee_contributions' => $gross * $employeeRate,
            'net' => $gross * (1 - $employeeRate),
            'employee_rate' => $employeeRate,
        ];
    }

    private function scaleHospital(array $hospital, float $growth): array
    {
        $scaled = $hospital;
        foreach (['base_gross', 'oncall_weekday_gross', 'oncall_weekend_gross', 'oncall_holiday_gross', 'oncall_gross', 'total_gross', 'employee_contributions', 'net'] as $key) {
            $scaled[$key] = (float)$hospital[$key] * $growth;
        }
        return $scaled;
    }

    private function stepFromSeniority(float $years): int
    {
        $years = max(0, $years);
        $thresholds = [2, 4, 6, 8, 10, 12, 14, 16, 20, 24, 28, 32];
        $step = 1;
        foreach ($thresholds as $threshold) {
            if ($years >= $threshold) {
                $step++;
            } else {
                break;
            }
        }
        return min(13, $step);
    }

    private function taxParts(array $p): float
    {
        if (($p['tax_parts_mode'] ?? 'auto') === 'manual') {
            return max(1.0, (float)$p['tax_parts_manual']);
        }
        $parts = in_array($p['marital_status'], ['married', 'pacs'], true) ? 2.0 : 1.0;
        $children = max(0, (int)$p['children_count']);
        for ($i = 1; $i <= $children; $i++) {
            $parts += $i <= 2 ? 0.5 : 1.0;
        }
        if ((int)$p['single_parent'] === 1 && $children > 0 && !in_array($p['marital_status'], ['married', 'pacs'], true)) {
            $parts += 0.5;
        }
        $parts += 0.5 * max(0, (int)$p['disabled_extra_half_parts']);
        return $parts;
    }

    private function professionalYear(array $p, string $sector, float $growth, float $ramp): array
    {
        $weeks = (float)$p['working_weeks'];
        $calc = fn(string $inputKey, string $tariffKey): float =>
            (float)$p[$inputKey.'_week'] * $weeks * (float)$p['tariffs'][$sector.'_'.$tariffKey] * $growth * $ramp;
        $consult = $calc('consultations', 'consultation');
        $eeg = $calc('eeg', 'eeg');
        $emgStandard = $calc('emg_standard', 'emg_standard');
        $emgComplex = $calc('emg_complex', 'emg_complex');
        $gross = $consult + $eeg + $emgStandard + $emgComplex;
        $optamPrime = 0.0;
        if ($sector === 's2' && (int)$p['optam_enabled_s2'] === 1) {
            $optamPrime = $gross
                * min(1.0, max(0.0, (float)$p['optam_opposable_share']))
                * max(0.0, (float)$p['optam_specialty_charge_rate'])
                * min(1.0, max(0.0, (float)$p['optam_compliance_rate']));
        }
        return [
            'gross_consultations' => $consult,
            'gross_eeg' => $eeg,
            'gross_emg_standard' => $emgStandard,
            'gross_emg_complex' => $emgComplex,
            'gross_revenue' => $gross,
            'optam_prime' => $optamPrime,
            'hospital_royalty' => $gross * (float)$p['hospital_royalty_rate'],
            'professional_expenses' => $gross * (float)$p['professional_expense_rate'],
        ];
    }

    private function emptyActivity(): array
    {
        return [
            'gross_consultations' => 0.0,
            'gross_eeg' => 0.0,
            'gross_emg_standard' => 0.0,
            'gross_emg_complex' => 0.0,
            'gross_revenue' => 0.0,
            'optam_prime' => 0.0,
            'hospital_royalty' => 0.0,
            'professional_expenses' => 0.0,
        ];
    }

    private function mergeScenario(array $activity, array $professional, array $hospital, float $hospitalTax, float $hospitalAfterTax): array
    {
        return array_merge($activity, $professional, [
            'hospital_gross' => $hospital['total_gross'],
            'hospital_employee_contributions' => $hospital['employee_contributions'],
            'hospital_net' => $hospital['net'],
            'hospital_income_tax' => $hospitalTax,
            'hospital_after_tax' => $hospitalAfterTax,
            'available_income' => $professional['professional_available'] + $hospitalAfterTax,
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

    private function actComparison(array $p): array
    {
        $labels = [
            'consultation' => ['Consultations de neurologie', 'consultations'],
            'eeg' => ['EEG', 'eeg'],
            'emg_standard' => ['EMG standard', 'emg_standard'],
            'emg_complex' => ['EMG complexe', 'emg_complex'],
        ];
        $out = [];
        foreach ($labels as $key => $meta) {
            [$label, $inputKey] = $meta;
            $volume = (float)$p[$inputKey.'_week'] * (float)$p['working_weeks'];
            $s1 = (float)$p['tariffs']['s1_'.$key];
            $s2 = (float)$p['tariffs']['s2_'.$key];
            $out[] = [
                'key' => $key,
                'label' => $label,
                'annual_volume' => $volume,
                'tariff_s1' => $s1,
                'tariff_s2' => $s2,
                'annual_s1' => $volume * $s1,
                'annual_s2' => $volume * $s2,
                'difference' => $volume * ($s2 - $s1),
            ];
        }
        return $out;
    }
}
