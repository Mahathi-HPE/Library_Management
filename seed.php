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

$columnExists = static function (PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
};

$tableExists = static function (PDO $db, string $table): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
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

    if (!$columnExists($db, 'Borrows', 'BorrowId')) {
        $db->exec('ALTER TABLE Borrows DROP PRIMARY KEY');
        $db->exec('ALTER TABLE Borrows ADD COLUMN BorrowId INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
    }

    if (!$columnExists($db, 'Borrows', 'Bid')) {
        $db->exec('ALTER TABLE Borrows ADD COLUMN Bid INT NULL AFTER Mid');
    }
    if (!$columnExists($db, 'Borrows', 'BorrowStatus')) {
        $db->exec('ALTER TABLE Borrows ADD COLUMN BorrowStatus ENUM("Pending", "Approved", "Rejected") NULL AFTER Bdate');
    }
    if (!$columnExists($db, 'Borrows', 'ReturnStatus')) {
        $db->exec('ALTER TABLE Borrows ADD COLUMN ReturnStatus ENUM("Not Returned", "Pending", "Approved") DEFAULT "Not Returned"');
    }

    if ($columnExists($db, 'Borrows', 'Quantity')) {
        $db->exec('ALTER TABLE Borrows DROP COLUMN Quantity');
    }
    if ($columnExists($db, 'Borrows', 'RequestDate')) {
        $db->exec('ALTER TABLE Borrows DROP COLUMN RequestDate');
    }
    if ($columnExists($db, 'Borrows', 'ProcessedDate')) {
        $db->exec('ALTER TABLE Borrows DROP COLUMN ProcessedDate');
    }
    if ($columnExists($db, 'Borrows', 'ReturnRequestDate')) {
        $db->exec('ALTER TABLE Borrows DROP COLUMN ReturnRequestDate');
    }
    if ($columnExists($db, 'Borrows', 'ReturnedDate')) {
        $db->exec('ALTER TABLE Borrows DROP COLUMN ReturnedDate');
    }

    if ($tableExists($db, 'BorrowRequests')) {
        $db->exec('INSERT INTO Borrows (Cid, Mid, Bid, Bdate, BorrowStatus, Fine, FineStatus, ReturnStatus)
                   SELECT NULL, Mid, Bid, NULL, Status, 0, "NA", "Not Returned"
                   FROM BorrowRequests');
        $db->exec('DROP TABLE BorrowRequests');
    }

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
