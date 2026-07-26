<?php
declare(strict_types=1);

$defaults = require __DIR__.'/../config/defaults.php';
$userId = (int)$user['id'];
$currentId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$params = $defaults;
$message = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

if ($currentId && ($saved = $db->find($currentId, $userId))) {
    $params = array_replace_recursive($defaults, $saved['payload']);
    $params['name'] = $saved['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'simulate');
    $currentId = !empty($_POST['simulation_id']) ? (int)$_POST['simulation_id'] : null;
    if ($action === 'delete' && $currentId) {
        $db->delete($currentId, $userId);
        $_SESSION['flash'] = 'Simulation supprimée.';
        redirect('/?page=app');
    }
    if ($action === 'duplicate' && $currentId) {
        $newId = $db->duplicate($currentId, $userId);
        redirect('/?page=app&id='.$newId);
    }

    $params = $defaults;
    $params['name'] = trim((string)($_POST['name'] ?? $defaults['name']));
    foreach (['horizon_years','working_weeks','consultations_week','eeg_week','emg_standard_week','emg_complex_week','hospital_seniority_years','hospital_step_manual','hospital_practitioner_count','oncall_weekday_periods_service','oncall_weekend_periods_service','oncall_holiday_periods_service','oncall_weekday_rate','oncall_weekend_rate','oncall_holiday_rate','household_other_taxable_income','children_count','tax_parts_manual','annual_tax_reductions','annual_tax_credits','initial_assets','initial_debt','annual_real_estate_saving','micro_bnc_threshold','is_reduced_profit_limit'] as $key) {
        $params[$key] = num($_POST, $key, (float)$defaults[$key]);
    }
    foreach (['professional_expense_rate','hospital_royalty_rate','annual_growth','activity_ramp_year1','activity_ramp_year2','savings_rate','investment_return','inflation','micro_bnc_allowance_rate','company_remuneration_share','company_distribution_share','is_reduced_rate','is_standard_rate','dividend_flat_tax_rate','optam_opposable_share','optam_specialty_charge_rate','optam_compliance_rate','urssaf_effective_rate_s1','urssaf_effective_rate_s2'] as $key) {
        $params[$key] = num($_POST, $key, (float)$defaults[$key] * 100) / 100;
    }
    $allowed = array_keys(BusinessStructureCalculator::STRUCTURES);
    $params['structure_s1'] = in_array($_POST['structure_s1'] ?? '', $allowed, true) ? $_POST['structure_s1'] : 'ei_bnc';
    $params['structure_s2'] = in_array($_POST['structure_s2'] ?? '', $allowed, true) ? $_POST['structure_s2'] : 'selarl_is';
    $params['optam_enabled_s2'] = isset($_POST['optam_enabled_s2']) ? 1 : 0;
    $params['hospital_step_mode'] = ($_POST['hospital_step_mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto';
    $params['marital_status'] = in_array($_POST['marital_status'] ?? '', ['single','married','pacs','divorced','widowed'], true) ? $_POST['marital_status'] : 'single';
    $params['tax_parts_mode'] = ($_POST['tax_parts_mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto';
    $params['single_parent'] = isset($_POST['single_parent']) ? 1 : 0;
    foreach ($defaults['tariffs'] as $key => $value) {
        $params['tariffs'][$key] = num($_POST, 'tariff_'.$key, (float)$value);
    }
    if ($action === 'save') {
        $currentId = $db->save($currentId, $userId, $params['name'], $params);
        $message = 'Simulation enregistrée.';
    }
}

$result = (new FinancialSimulator(new TaxCalculator()))->simulate($params);
$last = end($result['rows']);
$savedList = $db->all($userId);
$structures = BusinessStructureCalculator::STRUCTURES;
function field(string $name, string $label, mixed $value, string $step='1'): void {
    echo '<label>'.e($label).'<input type="number" step="'.e($step).'" name="'.e($name).'" value="'.e((string)$value).'"></label>';
}
function breakdown(array $x): void {
    $rows = [
        'Chiffre d’affaires libéral'=>$x['gross_revenue'],
        'Prime OPTAM estimée'=>$x['optam_prime'],
        'Redevance hospitalière'=>-$x['hospital_royalty'],
        'Charges professionnelles'=>-$x['professional_expenses'],
        'Urssaf'=>-$x['urssaf'],
        'CARMF'=>-$x['carmf'],
        'Impôt sur les sociétés'=>-$x['corporate_tax'],
        'IR professionnel'=>-$x['personal_professional_tax'],
        'Fiscalité des dividendes'=>-$x['dividend_tax'],
        'Libéral net disponible'=>$x['professional_available'],
        'Trésorerie conservée en société'=>$x['company_retained_earnings'],
    ];
    foreach ($rows as $label=>$value) echo '<tr><th>'.e($label).'</th><td>'.money((float)$value).'</td></tr>';
}
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Med Finances — Structures, S1 et S2 OPTAM</title><link rel="stylesheet" href="/assets/app.css"></head><body>
<header class="topbar"><div><strong>Med Finances</strong><span>Neurologie libérale hospitalière</span></div><nav><span><?=e($user['full_name'])?></span><form method="post" action="/?page=logout"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><button>Déconnexion</button></form></nav></header>
<main><?php if ($message): ?><div class="notice"><?=e($message)?></div><?php endif; ?>
<section class="hero"><div><p class="eyebrow">DÉCISION DE CARRIÈRE ET DE STRUCTURE</p><h1>Secteur 1 immédiat ou secteur 2 OPTAM après un an ?</h1><p>Les revenus hospitaliers, les flux libéraux et le patrimoine sont présentés séparément. Chaque trajectoire peut utiliser une structure différente.</p></div><div class="decision-card"><span>Écart patrimonial final S2 − S1</span><strong><?=money($result['final_wealth_difference'])?></strong><small><?= $result['crossing_year'] ? 'Rattrapage en année '.$result['crossing_year'] : 'Pas de rattrapage sur l’horizon' ?></small></div></section>
<section class="kpis"><article><span>Patrimoine final S1</span><strong><?=money($last['wealth_s1'])?></strong></article><article><span>Patrimoine final S2</span><strong><?=money($last['wealth_s2'])?></strong></article><article><span>Coût de l’attente année 1</span><strong><?=money($result['waiting_cost_year1'])?></strong></article><article><span>Écart revenu cumulé</span><strong><?=money($result['final_income_difference'])?></strong></article></section>
<div class="layout"><aside class="sidebar"><h2>Simulations</h2><?php foreach($savedList as $s): ?><a class="saved" href="/?page=app&id=<?=$s['id']?>"><strong><?=e($s['name'])?></strong><small><?=e(substr($s['updated_at'],0,10))?></small></a><?php endforeach; ?><div class="legal-note"><strong>Repères</strong><p>BNC est une catégorie fiscale, non une forme sociale. IS signifie impôt sur les sociétés. L’OPTAM est modélisé comme une prime conventionnelle paramétrable, pas comme une cotisation autonome.</p></div></aside><div class="content">
<form method="post" class="panel form-panel"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="simulation_id" value="<?=$currentId ?: ''?>"><div class="panel-head"><h2>Hypothèses</h2><div class="actions"><button name="action" value="simulate">Recalculer</button><button name="action" value="save">Enregistrer</button><?php if($currentId): ?><button name="action" value="duplicate">Dupliquer</button><button class="danger" name="action" value="delete" data-confirm="Supprimer ?">Supprimer</button><?php endif; ?></div></div>
<label class="full">Nom<input name="name" value="<?=e((string)$params['name'])?>"></label>
<details open><summary>Trajectoires et structures</summary><div class="grid4">
<label>Structure S1<select name="structure_s1"><?php foreach($structures as $k=>$v): ?><option value="<?=e($k)?>" <?=$params['structure_s1']===$k?'selected':''?>><?=e($v)?></option><?php endforeach; ?></select></label>
<label>Structure S2 OPTAM<select name="structure_s2"><?php foreach($structures as $k=>$v): ?><option value="<?=e($k)?>" <?=$params['structure_s2']===$k?'selected':''?>><?=e($v)?></option><?php endforeach; ?></select></label>
<?php field('horizon_years','Horizon (années)',$params['horizon_years']); field('micro_bnc_threshold','Seuil micro-BNC (€)',$params['micro_bnc_threshold'],'100'); field('company_remuneration_share','Résultat affecté à la rémunération IS (%)',$params['company_remuneration_share']*100,'1'); field('company_distribution_share','Bénéfice distribué (%)',$params['company_distribution_share']*100,'1'); field('is_reduced_rate','IS réduit (%)',$params['is_reduced_rate']*100,'0.1'); field('is_reduced_profit_limit','Plafond IS réduit (€)',$params['is_reduced_profit_limit'],'100'); field('is_standard_rate','IS normal (%)',$params['is_standard_rate']*100,'0.1'); field('dividend_flat_tax_rate','PFU dividendes (%)',$params['dividend_flat_tax_rate']*100,'0.1'); ?>
<label>OPTAM S2<input type="checkbox" name="optam_enabled_s2" <?=$params['optam_enabled_s2']?'checked':''?>></label><?php field('optam_opposable_share','Part tarif opposable (%)',$params['optam_opposable_share']*100,'1'); field('optam_specialty_charge_rate','Taux charges spécialité OPTAM (%)',$params['optam_specialty_charge_rate']*100,'0.1'); field('optam_compliance_rate','Respect engagements OPTAM (%)',$params['optam_compliance_rate']*100,'1'); ?>
</div></details>
<details open><summary>Activité libérale et prélèvements</summary><div class="grid4"><?php field('working_weeks','Semaines travaillées',$params['working_weeks']); field('consultations_week','Consultations/semaine',$params['consultations_week'],'0.5'); field('eeg_week','EEG/semaine',$params['eeg_week'],'0.5'); field('emg_standard_week','EMG standard/semaine',$params['emg_standard_week'],'0.5'); field('emg_complex_week','EMG complexe/semaine',$params['emg_complex_week'],'0.5'); field('professional_expense_rate','Charges professionnelles (%)',$params['professional_expense_rate']*100,'0.1'); field('hospital_royalty_rate','Redevance hospitalière (%)',$params['hospital_royalty_rate']*100,'0.1'); field('urssaf_effective_rate_s1','Urssaf effective S1 (%)',$params['urssaf_effective_rate_s1']*100,'0.1'); field('urssaf_effective_rate_s2','Urssaf effective S2 (%)',$params['urssaf_effective_rate_s2']*100,'0.1'); field('activity_ramp_year1','Montée en charge année 1 (%)',$params['activity_ramp_year1']*100,'1'); field('activity_ramp_year2','Montée en charge année 2 (%)',$params['activity_ramp_year2']*100,'1'); field('annual_growth','Croissance annuelle (%)',$params['annual_growth']*100,'0.1'); ?></div><div class="tariff-grid"><div></div><strong>S1</strong><strong>S2</strong><?php foreach(['consultation'=>'Consultation','eeg'=>'EEG','emg_standard'=>'EMG standard','emg_complex'=>'EMG complexe'] as $k=>$label): ?><span><?=e($label)?></span><input name="tariff_s1_<?=$k?>" type="number" step="0.01" value="<?=$params['tariffs']['s1_'.$k]?>"><input name="tariff_s2_<?=$k?>" type="number" step="0.01" value="<?=$params['tariffs']['s2_'.$k]?>"><?php endforeach; ?></div></details>
<details><summary>Hospitalier et foyer fiscal</summary><div class="grid4"><?php field('hospital_seniority_years','Ancienneté PH (années)',$params['hospital_seniority_years'],'0.5'); ?><label>Échelon<select name="hospital_step_mode"><option value="auto" <?=$params['hospital_step_mode']==='auto'?'selected':''?>>Automatique</option><option value="manual" <?=$params['hospital_step_mode']==='manual'?'selected':''?>>Manuel</option></select></label><?php field('hospital_step_manual','Échelon manuel',$params['hospital_step_manual']); field('hospital_practitioner_count','Praticiens partageant les astreintes',$params['hospital_practitioner_count']); field('oncall_weekday_periods_service','Astreintes semaine service/an',$params['oncall_weekday_periods_service']); field('oncall_weekend_periods_service','Astreintes week-end service/an',$params['oncall_weekend_periods_service']); field('oncall_holiday_periods_service','Astreintes fériés service/an',$params['oncall_holiday_periods_service']); field('oncall_weekday_rate','Tarif astreinte semaine (€)',$params['oncall_weekday_rate'],'0.01'); field('oncall_weekend_rate','Tarif astreinte week-end (€)',$params['oncall_weekend_rate'],'0.01'); field('oncall_holiday_rate','Tarif astreinte férié (€)',$params['oncall_holiday_rate'],'0.01'); ?><label>Situation<select name="marital_status"><?php foreach(['single'=>'Célibataire','married'=>'Marié','pacs'=>'Pacsé','divorced'=>'Divorcé','widowed'=>'Veuf'] as $k=>$v): ?><option value="<?=$k?>" <?=$params['marital_status']===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></label><?php field('children_count','Enfants à charge',$params['children_count']); field('household_other_taxable_income','Autres revenus imposables du foyer (€)',$params['household_other_taxable_income'],'100'); ?><label>Parts<select name="tax_parts_mode"><option value="auto" <?=$params['tax_parts_mode']==='auto'?'selected':''?>>Automatiques</option><option value="manual" <?=$params['tax_parts_mode']==='manual'?'selected':''?>>Manuelles</option></select></label><?php field('tax_parts_manual','Nombre de parts manuel',$params['tax_parts_manual'],'0.5'); ?></div></details>
<details><summary>Patrimoine</summary><div class="grid4"><?php field('initial_assets','Actifs initiaux (€)',$params['initial_assets'],'1000'); field('initial_debt','Dette initiale (€)',$params['initial_debt'],'1000'); field('savings_rate','Taux d’épargne personnelle (%)',$params['savings_rate']*100,'1'); field('annual_real_estate_saving','Épargne immobilière annuelle (€)',$params['annual_real_estate_saving'],'100'); field('investment_return','Rendement annuel (%)',$params['investment_return']*100,'0.1'); field('inflation','Inflation (%)',$params['inflation']*100,'0.1'); ?></div></details></form>
<section class="comparison"><article class="panel"><h2>Secteur 1 — <?=e($last['s1']['structure_label'])?></h2><table><?php breakdown($last['s1']); ?><tr class="separator"><th>Hospitalier brut</th><td><?=money($last['s1']['hospital_gross'])?></td></tr><tr><th>Retenues hospitalières</th><td><?=money(-$last['s1']['hospital_employee_contributions'])?></td></tr><tr><th>IR hospitalier</th><td><?=money(-$last['s1']['hospital_income_tax'])?></td></tr><tr><th>Hospitalier net après IR</th><td><?=money($last['s1']['hospital_after_tax'])?></td></tr></table></article><article class="panel"><h2>Secteur 2 OPTAM — <?=e($last['s2']['structure_label'])?></h2><table><?php breakdown($last['s2']); ?><tr class="separator"><th>Hospitalier brut</th><td><?=money($last['s2']['hospital_gross'])?></td></tr><tr><th>Retenues hospitalières</th><td><?=money(-$last['s2']['hospital_employee_contributions'])?></td></tr><tr><th>IR hospitalier</th><td><?=money(-$last['s2']['hospital_income_tax'])?></td></tr><tr><th>Hospitalier net après IR</th><td><?=money($last['s2']['hospital_after_tax'])?></td></tr></table></article></section>
<section class="panel"><h2>Classement des structures — année stabilisée</h2><div class="comparison"><div><h3>Secteur 1</h3><table><tr><th>Structure</th><th>Net personnel</th><th>Valeur économique</th></tr><?php foreach($result['structure_comparison_s1'] as $r): ?><tr><td><?=e($r['structure_label'])?><?=!$r['eligible']?' ⚠':''?></td><td><?=money($r['professional_available'])?></td><td><?=money($r['economic_value'])?></td></tr><?php endforeach; ?></table></div><div><h3>Secteur 2 OPTAM</h3><table><tr><th>Structure</th><th>Net personnel</th><th>Valeur économique</th></tr><?php foreach($result['structure_comparison_s2'] as $r): ?><tr><td><?=e($r['structure_label'])?><?=!$r['eligible']?' ⚠':''?></td><td><?=money($r['professional_available'])?></td><td><?=money($r['economic_value'])?></td></tr><?php endforeach; ?></table></div></div></section>
</div></div></main><script src="/assets/app.js"></script></body></html>
