<?php
declare(strict_types=1);

class User extends Model
{
    public function findByUsernameAndRole(string $username, string $role): ?array
    {
        return $this->fetchOne(
            'SELECT u.Uid, u.Username, u.Password, r.RName
             FROM Users u
             INNER JOIN UserRole ur ON ur.Uid = u.Uid
             INNER JOIN Roles r ON r.Rid = ur.Rid
             WHERE u.Username = :username AND r.RName = :role
             LIMIT 1',
            [
            ':username' => $username,
            ':role' => $role,
            ]
        );
    }

    public function findByUsername(string $username): ?array
    {
        return $this->fetchOne('SELECT * FROM Users WHERE Username = :username LIMIT 1', [':username' => $username]);
    }

    public function create(string $username, string $password): int
    {
        $stmt = $this->db->prepare('INSERT INTO Users (Username, Password) VALUES (:username, :password)');
        $stmt->execute([
            ':username' => $username,
            ':password' => password_hash($password, PASSWORD_BCRYPT),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function ensureRole(string $role): int
    {
        $row = $this->fetchOne('SELECT Rid FROM Roles WHERE RName = :role LIMIT 1', [':role' => $role]);

        if ($row) {
            return (int) $row['Rid'];
        }

        $insert = $this->db->prepare('INSERT INTO Roles (RName) VALUES (:role)');
        $insert->execute([':role' => $role]);

        return (int) $this->db->lastInsertId();
    }

    public function assignRole(int $uid, int $rid): void
    {
        $this->db->prepare('INSERT INTO UserRole (Rid, Uid) VALUES (:rid, :uid)')->execute([
            ':rid' => $rid,
            ':uid' => $uid,
        ]);
    }

    private function fetchOne(string $sql, array $params): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }
}
