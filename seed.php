<?php
declare(strict_types=1);

require_once __DIR__ . '/core/Database.php';

$db = Database::getInstance()->pdo();

$scalar = static function (PDO $db, string $sql) {
    return $db->query($sql)->fetchColumn();
};

$ensureRole = static function (PDO $db, string $role) use ($scalar): int {
    return (int) ($scalar($db, "SELECT Rid FROM Roles WHERE RName = '$role'") ?: tapInsert($db, "INSERT INTO Roles (RName) VALUES ('$role')"));
};

$ensureUser = static function (PDO $db, string $username, string $password) use ($scalar): int {
    return (int) ($scalar($db, "SELECT Uid FROM Users WHERE Username = '$username'")
        ?: tapPreparedInsert($db, 'INSERT INTO Users (Username, Password) VALUES (?, ?)', [$username, password_hash($password, PASSWORD_BCRYPT)]));
};

$ensureMember = static function (PDO $db, string $email) use ($scalar): int {
    return (int) ($scalar($db, "SELECT Mid FROM Members WHERE MemEmail = '$email'")
        ?: tapPreparedInsert($db, 'INSERT INTO Members (MemName, MemEmail, MemLoc) VALUES (?, ?, ?)', ['Sample User', $email, 'Sample City']));
};

$ensureLink = static function (PDO $db, string $table, string $left, int $leftId, string $right, int $rightId): void {
    if (!$db->query("SELECT 1 FROM $table WHERE $left = $leftId AND $right = $rightId")->fetch()) {
        $db->exec("INSERT INTO $table ($left, $right) VALUES ($leftId, $rightId)");
    }
};

try {
    $db->beginTransaction();

    $db->exec('CREATE TABLE IF NOT EXISTS UserMember (
        Uid INT,
        Mid INT,
        PRIMARY KEY (Uid, Mid),
        FOREIGN KEY (Uid) REFERENCES Users(Uid) ON DELETE CASCADE,
        FOREIGN KEY (Mid) REFERENCES Members(Mid) ON DELETE CASCADE
    )');

    $userRid = $ensureRole($db, 'User');
    $adminRid = $ensureRole($db, 'Admin');
    $memberUid = $ensureUser($db, 'sampleuser@library.com', 'password123');
    $adminUid = $ensureUser($db, 'sampleadmin', 'password123');
    $memberMid = $ensureMember($db, 'sampleuser@library.com');

    $ensureLink($db, 'UserRole', 'Uid', $memberUid, 'Rid', $userRid);
    $ensureLink($db, 'UserRole', 'Uid', $adminUid, 'Rid', $adminRid);
    $ensureLink($db, 'UserMember', 'Uid', $memberUid, 'Mid', $memberMid);

    $db->commit();
    echo "✓ Seeding done.\nMember: sampleuser@library.com / password123\nAdmin: sampleadmin / password123\n";
} catch (Throwable $e) {
    $db->rollBack();
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

function tapInsert(PDO $db, string $sql): string
{
    $db->exec($sql);
    return (string) $db->lastInsertId();
}

function tapPreparedInsert(PDO $db, string $sql, array $params): string
{
    $db->prepare($sql)->execute($params);
    return (string) $db->lastInsertId();
}
