<?php
declare(strict_types=1);

final class MedicalContributionsCalculator
{
    public function calculate(float $base, string $sector, array $p, string $socialMode = 'pamc'): array
    {
        $base = max(0.0, $base);
        if ($base <= 0.0) {
            return $this->emptyResult();
        }

        if ($socialMode === 'assimilated_salary') {
            $urssaf = $base * max(0.0, (float)$p['selas_urssaf_effective_rate']);
            $carmf = $base * max(0.0, (float)$p['selas_carmf_effective_rate']);
            return [
                'urssaf' => $urssaf,
                'carmf' => $carmf,
                'total' => $urssaf + $carmf,
                'carmf_base' => $carmf,
                'carmf_complementary' => 0.0,
                'carmf_asv' => 0.0,
                'carmf_invalidity_death' => 0.0,
                'cpam_participation' => 0.0,
                'social_mode' => $socialMode,
            ];
        }

        $pass = max(1.0, (float)$p['social_security_pass']);
        $baseT1 = min($base, $pass) * (float)$p['carmf_base_t1_rate'];
        // La tranche 2 s'applique sur le revenu dans la limite de 5 PASS, conformément aux exemples CARMF.
        $baseT2 = min($base, 5 * $pass) * (float)$p['carmf_base_t2_rate'];
        $cpamParticipation = 0.0;
        if ($sector === 's1') {
            $rate = $base < 1.4 * $pass
                ? (float)$p['carmf_s1_cpam_rate_low']
                : ($base < 2.5 * $pass ? (float)$p['carmf_s1_cpam_rate_mid'] : (float)$p['carmf_s1_cpam_rate_high']);
            $cpamParticipation = min($baseT1, $base * $rate);
        }
        $carmfBase = max(0.0, $baseT1 + $baseT2 - $cpamParticipation);
        $carmfComplementary = min($base, 3.5 * $pass) * (float)$p['carmf_complementary_rate'];
        $asvFixed = $sector === 's1' ? (float)$p['carmf_asv_fixed_s1'] : (float)$p['carmf_asv_fixed_s2'];
        $asvRate = $sector === 's1' ? (float)$p['carmf_asv_rate_s1'] : (float)$p['carmf_asv_rate_s2'];
        $carmfAsv = $asvFixed + min($base, 5 * $pass) * $asvRate;
        if ($base < $pass) {
            $carmfInvalidity = (float)$p['carmf_invalidity_min'];
        } elseif ($base <= 3 * $pass) {
            $carmfInvalidity = (float)$p['carmf_invalidity_mid_fixed'] + $base * (float)$p['carmf_invalidity_mid_rate'];
        } else {
            $carmfInvalidity = (float)$p['carmf_invalidity_max'];
        }
        $carmf = $carmfBase + $carmfComplementary + $carmfAsv + $carmfInvalidity;
        $urssafRate = $sector === 's1' ? (float)$p['urssaf_effective_rate_s1'] : (float)$p['urssaf_effective_rate_s2'];
        $urssaf = $base * max(0.0, $urssafRate);

        return [
            'urssaf' => $urssaf,
            'carmf' => $carmf,
            'total' => $urssaf + $carmf,
            'carmf_base' => $carmfBase,
            'carmf_complementary' => $carmfComplementary,
            'carmf_asv' => $carmfAsv,
            'carmf_invalidity_death' => $carmfInvalidity,
            'cpam_participation' => $cpamParticipation,
            'social_mode' => $socialMode,
        ];
    }

    private function emptyResult(): array
    {
        return [
            'urssaf' => 0.0,
            'carmf' => 0.0,
            'total' => 0.0,
            'carmf_base' => 0.0,
            'carmf_complementary' => 0.0,
            'carmf_asv' => 0.0,
            'carmf_invalidity_death' => 0.0,
            'cpam_participation' => 0.0,
            'social_mode' => 'pamc',
        ];
    }
}
