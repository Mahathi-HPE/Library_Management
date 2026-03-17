<?php
declare(strict_types=1);

class Member extends Model
{
    public function __construct()
    {
        parent::__construct();
        $this->ensureUserMemberTable();
    }

    public function findByEmail(string $email): ?array
    {
        return $this->fetchOne('SELECT * FROM Members WHERE MemEmail = :email LIMIT 1', [':email' => $email]);
    }

    public function findByUserId(int $uid): ?array
    {
        return $this->fetchOne(
            'SELECT m.*
             FROM Members m
             INNER JOIN UserMember um ON um.Mid = m.Mid
             WHERE um.Uid = :uid
             LIMIT 1',
            [':uid' => $uid]
        );
    }

    public function findByUser(int $uid, string $username): ?array
    {
        return $this->findByUserId($uid) ?? $this->findByEmail($username);
    }

    public function create(string $name, string $email, string $location): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO Members (MemName, MemEmail, MemLoc) VALUES (:name, :email, :location)'
        );
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':location' => $location,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function linkUser(int $uid, int $mid): void
    {
        $this->db->prepare('INSERT INTO UserMember (Uid, Mid) VALUES (:uid, :mid)')->execute([
            ':uid' => $uid,
            ':mid' => $mid,
        ]);
    }

    private function fetchOne(string $sql, array $params): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    private function ensureUserMemberTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS UserMember (
                Uid INT,
                Mid INT,
                PRIMARY KEY (Uid, Mid),
                FOREIGN KEY (Uid) REFERENCES Users(Uid) ON DELETE CASCADE,
                FOREIGN KEY (Mid) REFERENCES Members(Mid) ON DELETE CASCADE
            )'
        );
    }
}
