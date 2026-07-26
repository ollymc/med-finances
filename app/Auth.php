<?php
declare(strict_types=1);

final class Auth
{
    public function __construct(private Database $db) {}

    public function user(): ?array
    {
        $id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        return $id > 0 ? $this->db->findUserById($id) : null;
    }

    public function attempt(string $login, string $password): bool
    {
        $user = $this->db->findUserByLogin($login);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        return true;
    }

    public function register(string $fullName, string $username, string $password): array
    {
        $fullName = trim($fullName);
        $username = strtolower(trim($username));
        if (strlen($fullName) < 2) return [false, 'Veuillez saisir votre identité complète.'];
        if (!preg_match('/^[a-z0-9._-]{3,40}$/', $username)) return [false, 'Le login doit contenir 3 à 40 caractères : lettres, chiffres, point, tiret ou underscore.'];
        if (strlen($password) < 10) return [false, 'Le mot de passe doit contenir au moins 10 caractères.'];
        if ($this->db->findUserByLogin($username)) return [false, 'Ce login est déjà utilisé.'];

        $id = $this->db->createUser($fullName, $username, password_hash($password, PASSWORD_DEFAULT));
        $this->db->claimLegacySimulations($id);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        return [true, 'Compte créé.'];
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
