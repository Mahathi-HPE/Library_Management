<?php
declare(strict_types=1);

class Borrow extends Model
{
    private const MONTHLY_BORROW_LIMIT = 7;
    private const MAX_COPIES_PER_BOOK = 3;
    private const FINE_FREE_DAYS = 14;
    private const FINE_PER_DAY = 5;

    public function borrowBook(int $bid, int $mid, int $quantity = 1, bool $skipLimits = false): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            if (!$this->createBorrowEntries($bid, $mid, $quantity, $skipLimits)) {
                $this->db->rollBack();
                return false;
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    private function createBorrowEntries(int $bid, int $mid, int $quantity, bool $skipLimits): bool
    {
        // Check if member would exceed maximum copies after borrowing
        $currentCount = $this->getActiveBorrowCount($bid, $mid);
        if (!$skipLimits && ($currentCount + $quantity) > self::MAX_COPIES_PER_BOOK) {
            return false;
        }

        // Check if enough copies are available
        if (!$this->isAvailable($bid, $quantity)) {
            return false;
        }

        if (!$skipLimits && ($this->borrowedThisMonth($mid) + $quantity) > self::MONTHLY_BORROW_LIMIT) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT Cid FROM Copies WHERE Bid = :bid AND Status = "Available" ORDER BY Cid LIMIT ' . $quantity
        );
        $stmt->execute([':bid' => $bid]);
        $copies = $stmt->fetchAll();

        if (count($copies) < $quantity) {
            return false;
        }

        $insert = $this->db->prepare(
              'INSERT INTO Borrows (Cid, Mid, Bid, Bdate, Fine, FineStatus, ReturnStatus, BorrowStatus)
               VALUES (:cid, :mid, :bid, CURDATE(), 0, "NA", "Not Returned", "Approved")'
        );
        $update = $this->db->prepare('UPDATE Copies SET Status = "Rented" WHERE Cid = :cid');

        foreach ($copies as $copy) {
            $cid = (int) $copy['Cid'];
            $insert->execute([':cid' => $cid, ':mid' => $mid, ':bid' => $bid]);
            $update->execute([':cid' => $cid]);
        }

        return true;
    }

    public function requestBook(int $bid, int $mid, int $quantity = 1): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                'INSERT INTO Borrows (Cid, Mid, Bid, Bdate, Fine, FineStatus, ReturnStatus, BorrowStatus)
                 VALUES (NULL, :mid, :bid, NULL, 0, "NA", "Not Returned", "Pending")'
            );

            for ($i = 0; $i < $quantity; $i++) {
                $stmt->execute([
                    ':bid' => $bid,
                    ':mid' => $mid,
                ]);
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function getPendingBorrowRequests(): array
    {
        $stmt = $this->db->prepare(
            'SELECT br.BorrowId, br.Bid, br.Mid, b.Title, m.MemName
             FROM Borrows br
             INNER JOIN Books b ON b.Bid = br.Bid
             INNER JOIN Members m ON m.Mid = br.Mid
             WHERE br.Cid IS NULL AND br.BorrowStatus = "Pending"
             ORDER BY br.BorrowId ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function approveBorrowRequest(int $borrowId): bool
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                'SELECT BorrowId, Bid, Mid
                 FROM Borrows
                 WHERE BorrowId = :borrowId AND Cid IS NULL AND BorrowStatus = "Pending"
                 FOR UPDATE'
            );
            $stmt->execute([':borrowId' => $borrowId]);
            $request = $stmt->fetch();

            if (!$request) {
                $this->db->rollBack();
                return false;
            }

            $bid = (int) $request['Bid'];
            $mid = (int) $request['Mid'];

            if (($this->getActiveBorrowCount($bid, $mid) + 1) > self::MAX_COPIES_PER_BOOK) {
                $this->db->rollBack();
                return false;
            }

            if (($this->borrowedThisMonth($mid) + 1) > self::MONTHLY_BORROW_LIMIT) {
                $this->db->rollBack();
                return false;
            }

            $copyStmt = $this->db->prepare(
                'SELECT Cid FROM Copies WHERE Bid = :bid AND Status = "Available" ORDER BY Cid LIMIT 1 FOR UPDATE'
            );
            $copyStmt->execute([':bid' => $bid]);
            $copy = $copyStmt->fetch();
            if (!$copy) {
                $this->db->rollBack();
                return false;
            }
            $cid = (int) $copy['Cid'];

            $copyUpdate = $this->db->prepare('UPDATE Copies SET Status = "Rented" WHERE Cid = :cid AND Status = "Available"');
            $copyUpdate->execute([':cid' => $cid]);
            if ($copyUpdate->rowCount() !== 1) {
                $this->db->rollBack();
                return false;
            }

            $update = $this->db->prepare(
                'UPDATE Borrows
                 SET Cid = :cid,
                     Bdate = CURDATE(),
                     BorrowStatus = "Approved",
                     ReturnStatus = "Not Returned",
                     Fine = 0,
                     FineStatus = "NA"
                 WHERE BorrowId = :borrowId AND Cid IS NULL AND BorrowStatus = "Pending"'
            );
            $update->execute([':borrowId' => $borrowId, ':cid' => $cid]);

            if ($update->rowCount() !== 1) {
                $this->db->rollBack();
                return false;
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function rejectBorrowRequest(int $borrowId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE Borrows
             SET BorrowStatus = "Rejected"
             WHERE BorrowId = :borrowId AND Cid IS NULL AND BorrowStatus = "Pending"'
        );
        $stmt->execute([':borrowId' => $borrowId]);
        return $stmt->rowCount() === 1;
    }

    public function getBorrowRequestsByMember(int $mid): array
    {
        $stmt = $this->db->prepare(
            'SELECT br.BorrowId, br.Bid, br.BorrowStatus AS Status, b.Title
             FROM Borrows br
             INNER JOIN Books b ON b.Bid = br.Bid
             WHERE br.Mid = :mid AND br.BorrowStatus IS NOT NULL
             ORDER BY br.BorrowId DESC'
        );
        $stmt->execute([':mid' => $mid]);
        return $stmt->fetchAll();
    }

    public function getActiveBorrowCount(int $bid, int $mid): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM Borrows br
             INNER JOIN Copies c ON c.Cid = br.Cid
               WHERE br.Mid = :mid AND c.Bid = :bid AND c.Status = "Rented" AND br.ReturnStatus <> "Approved"'
        );
        $stmt->execute([':mid' => $mid, ':bid' => $bid]);
        return (int) $stmt->fetchColumn();
    }

    public function getAvailableCopiesCount(int $bid): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM Copies WHERE Bid = :bid AND Status = "Available"'
        );
        $stmt->execute([':bid' => $bid]);
        return (int) $stmt->fetchColumn();
    }

    public function isAvailable(int $bid, int $quantity = 1): bool
    {
        return $this->getAvailableCopiesCount($bid) >= $quantity;
    }

    public function borrowedThisMonth(int $mid): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM Borrows
             WHERE Mid = :mid
             AND YEAR(Bdate) = YEAR(CURDATE())
             AND MONTH(Bdate) = MONTH(CURDATE())'
        );
        $stmt->execute([':mid' => $mid]);

        return (int) $stmt->fetchColumn();
    }

    public function remainingThisMonth(int $mid): int
    {
        return max(0, self::MONTHLY_BORROW_LIMIT - $this->borrowedThisMonth($mid));
    }

    public function currentBorrowed(int $mid): array
    {
        $sql = 'SELECT b.Title, b.Price,
                       GROUP_CONCAT(DISTINCT a.AuthName SEPARATOR ", ") AS AuthName,
                                             agg.Bid,
                                             agg.Copies,
                                             agg.Bdate,
                                             agg.Fine,
                                             CASE WHEN agg.Fine > 0 THEN "Not Paid" ELSE "NA" END AS FineStatus
                FROM (
                    SELECT c.Bid,
                           COUNT(DISTINCT c.Cid) AS Copies,
                           MAX(br.Bdate) AS Bdate,
                           SUM(GREATEST(DATEDIFF(CURDATE(), br.Bdate) - ' . self::FINE_FREE_DAYS . ', 0) * ' . self::FINE_PER_DAY . ') AS Fine
                    FROM Borrows br
                    INNER JOIN Copies c ON c.Cid = br.Cid
                    WHERE br.Mid = :mid AND c.Status = "Rented" AND br.ReturnStatus <> "Approved"
                    GROUP BY c.Bid
                ) agg
                INNER JOIN Books b ON b.Bid = agg.Bid
                LEFT JOIN BookAuthor ba ON ba.Bid = b.Bid
                LEFT JOIN Author a ON a.Aid = ba.Aid
                GROUP BY agg.Bid, b.Title, b.Price, agg.Copies, agg.Bdate, agg.Fine
                ORDER BY agg.Bdate DESC, b.Title';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':mid' => $mid]);
        return $stmt->fetchAll();
    }

    public function currentReturnable(int $mid): array
    {
        $sql = 'SELECT b.Title, b.Price,
                       GROUP_CONCAT(DISTINCT a.AuthName SEPARATOR ", ") AS AuthName,
                                             agg.Bid,
                                             agg.Copies,
                                             agg.Bdate
                FROM (
                    SELECT c.Bid,
                           COUNT(DISTINCT c.Cid) AS Copies,
                           MAX(br.Bdate) AS Bdate
                    FROM Borrows br
                    INNER JOIN Copies c ON c.Cid = br.Cid
                    WHERE br.Mid = :mid
                      AND c.Status = "Rented"
                      AND br.ReturnStatus = "Not Returned"
                    GROUP BY c.Bid
                ) agg
                INNER JOIN Books b ON b.Bid = agg.Bid
                LEFT JOIN BookAuthor ba ON ba.Bid = b.Bid
                LEFT JOIN Author a ON a.Aid = ba.Aid
                GROUP BY agg.Bid, b.Title, b.Price, agg.Copies, agg.Bdate
                ORDER BY agg.Bdate DESC, b.Title';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':mid' => $mid]);
        return $stmt->fetchAll();
    }

    public function requestReturnBookCopies(int $bid, int $mid, int $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                'SELECT br.BorrowId, c.Cid
                 FROM Borrows br
                 INNER JOIN Copies c ON c.Cid = br.Cid
                                 WHERE br.Mid = :mid
                                     AND c.Bid = :bid
                                     AND c.Status = "Rented"
                                     AND br.ReturnStatus = "Not Returned"
                 ORDER BY br.BorrowId
                 LIMIT ' . $quantity
            );
            $stmt->execute([
                ':mid' => $mid,
                ':bid' => $bid,
            ]);
            $copies = $stmt->fetchAll();

            if (count($copies) < $quantity) {
                $this->db->rollBack();
                return false;
            }

            $update = $this->db->prepare(
                'UPDATE Borrows
                  SET ReturnStatus = "Pending"
                  WHERE BorrowId = :borrowId AND Mid = :mid AND ReturnStatus = "Not Returned"'
            );

            foreach ($copies as $copy) {
                $borrowId = (int) $copy['BorrowId'];
                $update->execute([':borrowId' => $borrowId, ':mid' => $mid]);
                if ($update->rowCount() !== 1) {
                    $this->db->rollBack();
                    return false;
                }
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function history(int $mid): array
    {
        $sql = 'SELECT b.Title, b.Price,
                  GROUP_CONCAT(DISTINCT a.AuthName SEPARATOR ", ") AS AuthName,
                                    COUNT(*) AS Copies,
                                    MAX(br.Bdate) AS Bdate
                FROM Borrows br
                INNER JOIN Copies c ON c.Cid = br.Cid
                INNER JOIN Books b ON b.Bid = c.Bid
                LEFT JOIN BookAuthor ba ON ba.Bid = b.Bid
                LEFT JOIN Author a ON a.Aid = ba.Aid
                WHERE br.Mid = :mid
              GROUP BY br.Mid, b.Bid, b.Title, b.Price
                                ORDER BY MAX(br.Bdate) DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':mid' => $mid]);
        return $stmt->fetchAll();
    }

    public function getPendingReturns(): array
    {
        $stmt = $this->db->prepare(
              'SELECT br.BorrowId, br.Cid, br.Mid, b.Title, m.MemName
             FROM Borrows br
             INNER JOIN Copies c ON c.Cid = br.Cid
             INNER JOIN Books b ON b.Bid = c.Bid
             INNER JOIN Members m ON m.Mid = br.Mid
               WHERE br.ReturnStatus = "Pending"
               ORDER BY br.BorrowId ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function approveReturn(int $borrowId): bool
    {
        try {
            $this->db->beginTransaction();

            $find = $this->db->prepare(
                'SELECT BorrowId, Cid
                 FROM Borrows
                  WHERE BorrowId = :borrowId AND ReturnStatus = "Pending"
                 FOR UPDATE'
            );
            $find->execute([':borrowId' => $borrowId]);
            $row = $find->fetch();

            if (!$row) {
                $this->db->rollBack();
                return false;
            }

            $cid = (int) $row['Cid'];

            $stmt = $this->db->prepare(
                'UPDATE Borrows
                  SET ReturnStatus = "Approved"
                  WHERE BorrowId = :borrowId AND ReturnStatus = "Pending"'
            );
            $stmt->execute([':borrowId' => $borrowId]);
            if ($stmt->rowCount() !== 1) {
                $this->db->rollBack();
                return false;
            }

            $update = $this->db->prepare('UPDATE Copies SET Status = "Available" WHERE Cid = :cid AND Status = "Rented"');
            $update->execute([':cid' => $cid]);
            if ($update->rowCount() !== 1) {
                $this->db->rollBack();
                return false;
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function adminManageUsersTable(): array
    {
         $sql = 'SELECT m.MemName, b.Title, b.Price,
                  GROUP_CONCAT(DISTINCT a.AuthName SEPARATOR ", ") AS AuthName,
                  COUNT(DISTINCT c.Cid) AS Copies,
                  MAX(br.Bdate) AS Bdate
                FROM Borrows br
                INNER JOIN Members m ON m.Mid = br.Mid
                INNER JOIN Copies c ON c.Cid = br.Cid
                INNER JOIN Books b ON b.Bid = c.Bid
                LEFT JOIN BookAuthor ba ON ba.Bid = b.Bid
                LEFT JOIN Author a ON a.Aid = ba.Aid
                WHERE c.Status = "Rented" AND br.ReturnStatus <> "Approved"
              GROUP BY m.Mid, b.Bid, m.MemName, b.Title, b.Price
              ORDER BY MAX(br.Bdate) DESC, m.MemName, b.Title';

        return $this->db->query($sql)->fetchAll();
    }

    public function adminFineTable(): array
    {
        $sql = 'SELECT m.MemName,
                       b.Title,
                       agg.Copies,
                       agg.Bdate,
                       agg.Fine,
                       CASE
                           WHEN agg.PaidCount > 0 THEN "Paid"
                           WHEN agg.Fine > 0 THEN "Not Paid"
                           ELSE "NA"
                       END AS FineStatus
                FROM (
                    SELECT br.Mid,
                           c.Bid,
                           COUNT(*) AS Copies,
                           MAX(br.Bdate) AS Bdate,
                           SUM(CASE WHEN br.FineStatus = "Paid" THEN 1 ELSE 0 END) AS PaidCount,
                           SUM(GREATEST(DATEDIFF(CURDATE(), br.Bdate) - ' . self::FINE_FREE_DAYS . ', 0) * ' . self::FINE_PER_DAY . ') AS Fine
                    FROM Borrows br
                    INNER JOIN Copies c ON c.Cid = br.Cid
                    WHERE c.Status = "Rented" AND br.ReturnStatus <> "Approved"
                    GROUP BY br.Mid, c.Bid
                ) agg
                INNER JOIN Members m ON m.Mid = agg.Mid
                INNER JOIN Books b ON b.Bid = agg.Bid
                WHERE agg.PaidCount > 0 OR agg.Fine > 0
                ORDER BY agg.Fine DESC, agg.Bdate DESC, m.MemName';

        return $this->db->query($sql)->fetchAll();
    }
}
