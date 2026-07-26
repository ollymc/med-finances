<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
session_name('MEDFINSESSID');
session_start();

require_once __DIR__.'/../app/helpers.php';
require_once __DIR__.'/../app/Database.php';
require_once __DIR__.'/../app/Auth.php';
require_once __DIR__.'/../app/TaxCalculator.php';
require_once __DIR__.'/../app/MedicalContributionsCalculator.php';
require_once __DIR__.'/../app/BusinessStructureCalculator.php';
require_once __DIR__.'/../app/FinancialSimulator.php';

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verify_csrf(): void {
    $given = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals(csrf_token(), $given)) {
        http_response_code(419);
        exit('Session expirée ou formulaire invalide. Rechargez la page.');
    }
}
function redirect(string $url): never { header('Location: '.$url); exit; }

$db = new Database(__DIR__.'/../storage/database/simulations.sqlite');
$auth = new Auth($db);
$user = $auth->user();
$page = (string)($_GET['page'] ?? ($user ? 'app' : 'home'));
$error = '';

if ($page === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/');
    verify_csrf();
    $auth->logout();
    redirect('/');
}

if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($auth->attempt((string)($_POST['login'] ?? ''), (string)($_POST['password'] ?? ''))) {
        redirect('/?page=app');
    }
    $error = 'Login ou mot de passe incorrect.';
}

if ($page === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ((string)($_POST['password'] ?? '') !== (string)($_POST['password_confirmation'] ?? '')) {
        $error = 'Les deux mots de passe ne correspondent pas.';
    } else {
        [$ok, $message] = $auth->register((string)($_POST['full_name'] ?? ''), (string)($_POST['login'] ?? ''), (string)($_POST['password'] ?? ''));
        if ($ok) redirect('/?page=app');
        $error = $message;
    }
}

$user = $auth->user();
if ($page === 'app') {
    if (!$user) redirect('/?page=login');
    require __DIR__.'/dashboard.php';
    exit;
}

if ($user && in_array($page, ['login','register'], true)) redirect('/?page=app');
$title = $page === 'register' ? 'Créer un compte' : ($page === 'login' ? 'Connexion' : 'Accueil');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Med Finances — <?=e($title)?></title>
<link rel="stylesheet" href="/assets/app.css">
</head>
<body class="public-body">
<header class="public-nav">
  <a href="/" class="brand-link"><strong>Med Finances</strong><span>Neurologie libérale</span></a>
  <nav><a href="/?page=login">Connexion</a><a class="nav-cta" href="/?page=register">Créer un compte</a></nav>
</header>
<main class="public-main">
<?php if ($page === 'login' || $page === 'register'): ?>
<section class="auth-shell">
  <div class="auth-intro">
    <p class="eyebrow">ESPACE PERSONNEL SÉCURISÉ</p>
    <h1><?= $page === 'register' ? 'Créer votre espace de simulation' : 'Accéder à vos simulations' ?></h1>
    <p>Vos hypothèses fiscales, professionnelles et patrimoniales sont séparées de celles des autres utilisateurs.</p>
  </div>
  <form method="post" class="auth-card">
    <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
    <h2><?= $page === 'register' ? 'Inscription' : 'Connexion' ?></h2>
    <?php if ($error): ?><div class="auth-error"><?=e($error)?></div><?php endif; ?>
    <?php if ($page === 'register'): ?>
      <label>Identité complète<input required autocomplete="name" name="full_name" value="<?=e((string)($_POST['full_name'] ?? ''))?>" placeholder="Dr Prénom Nom"></label>
    <?php endif; ?>
    <label>Login<input required autocomplete="username" name="login" value="<?=e((string)($_POST['login'] ?? ''))?>" placeholder="votre.login"></label>
    <label>Mot de passe<input required autocomplete="<?= $page === 'register' ? 'new-password' : 'current-password' ?>" type="password" name="password" minlength="10"></label>
    <?php if ($page === 'register'): ?>
      <label>Confirmer le mot de passe<input required autocomplete="new-password" type="password" name="password_confirmation" minlength="10"></label>
    <?php endif; ?>
    <button type="submit" class="auth-submit"><?= $page === 'register' ? 'Créer mon compte' : 'Se connecter' ?></button>
    <p class="auth-switch"><?= $page === 'register' ? 'Déjà inscrit ? <a href="/?page=login">Se connecter</a>' : 'Pas encore de compte ? <a href="/?page=register">Créer un compte</a>' ?></p>
  </form>
</section>
<?php else: ?>
<section class="landing-hero">
  <div>
    <p class="eyebrow">SIMULATEUR MÉDICAL, FISCAL ET PATRIMONIAL</p>
    <h1>Décider aujourd’hui avec une vision financière de toute votre carrière.</h1>
    <p class="landing-lead">Comparez un démarrage immédiat en secteur 1 à une attente d’un an pour le secteur 2 OPTAM, avec séparation du revenu hospitalier, du résultat libéral et du patrimoine.</p>
    <div class="landing-actions"><a class="primary-link" href="/?page=register">Créer mon espace</a><a class="secondary-link" href="/?page=login">J’ai déjà un compte</a></div>
  </div>
  <div class="landing-summary">
    <span>Comparateur stratégique</span><strong>Secteur 1 maintenant<br>vs secteur 2 OPTAM dans un an</strong>
    <ul><li>Consultations, EEG et EMG</li><li>Redevance hospitalière paramétrable</li><li>Urssaf, CARMF, IR, IS et OPTAM</li><li>Projection patrimoniale jusqu’à 40 ans</li></ul>
  </div>
</section>
<section class="feature-grid">
  <article><strong>Activité neurologique</strong><p>Volumes, tarifs et montée en charge pour les consultations, EEG et EMG.</p></article>
  <article><strong>Fiscalité médicale</strong><p>Comparaison EI-BNC, micro-BNC, EI à l’IS, SCP, SELARL et SELAS, séparément pour les secteurs 1 et 2 OPTAM.</p></article>
  <article><strong>Patrimoine</strong><p>Épargne, rendement, inflation, dette, immobilier et trajectoire cumulée.</p></article>
</section>
<?php endif; ?>
</main>
<footer>Med Finances — outil d’aide à la décision, sans valeur de conseil juridique, fiscal ou conventionnel.</footer>
</body></html>
