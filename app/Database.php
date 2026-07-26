<?php
declare(strict_types=1);

final class Database
{
    private PDO $pdo;

    public function __construct(string $path)
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Impossible de créer le répertoire de stockage.');
        }
        $this->pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->migrate();
    }

    private function migrate(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE COLLATE NOCASE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL
        )');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS simulations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NULL,
            name TEXT NOT NULL,
            payload TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )');
        $columns = $this->pdo->query('PRAGMA table_info(simulations)')->fetchAll();
        if (!in_array('user_id', array_column($columns, 'name'), true)) {
            $this->pdo->exec('ALTER TABLE simulations ADD COLUMN user_id INTEGER NULL REFERENCES users(id) ON DELETE CASCADE');
        }
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_simulations_user_updated ON simulations(user_id, updated_at DESC)');
    }

    public function createUser(string $fullName, string $username, string $passwordHash): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO users(full_name, username, password_hash, created_at) VALUES(?,?,?,?)');
        $stmt->execute([$fullName, $username, $passwordHash, date(DATE_ATOM)]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findUserByLogin(string $login): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = ? COLLATE NOCASE LIMIT 1');
        $stmt->execute([trim($login)]);
        return $stmt->fetch() ?: null;
    }

    public function findUserById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, full_name, username, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function claimLegacySimulations(int $userId): void
    {
        $countUsers = (int)$this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($countUsers === 1) {
            $stmt = $this->pdo->prepare('UPDATE simulations SET user_id = ? WHERE user_id IS NULL');
            $stmt->execute([$userId]);
        }
    }

    public function all(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, created_at, updated_at FROM simulations WHERE user_id = ? ORDER BY updated_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM simulations WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['payload'] = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);
        return $row;
    }

    public function save(?int $id, int $userId, string $name, array $payload): int
    {
        $now = date(DATE_ATOM);
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if ($id) {
            $stmt = $this->pdo->prepare('UPDATE simulations SET name=?, payload=?, updated_at=? WHERE id=? AND user_id=?');
            $stmt->execute([$name, $json, $now, $id, $userId]);
            if ($stmt->rowCount() === 0) throw new RuntimeException('Simulation introuvable ou accès refusé.');
            return $id;
        }
        $stmt = $this->pdo->prepare('INSERT INTO simulations(user_id,name,payload,created_at,updated_at) VALUES(?,?,?,?,?)');
        $stmt->execute([$userId, $name, $json, $now, $now]);
        return (int)$this->pdo->lastInsertId();
    }

    public function duplicate(int $id, int $userId): ?int
    {
        $row = $this->find($id, $userId);
        return $row ? $this->save(null, $userId, $row['name'] . ' (copie)', $row['payload']) : null;
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM simulations WHERE id=? AND user_id=?');
        $stmt->execute([$id, $userId]);
    }
}
