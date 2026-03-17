<?php
declare(strict_types=1);

class Borrow extends Model
{
    private const MONTHLY_BORROW_LIMIT = 7;
    private const MAX_COPIES_PER_BOOK = 3;
    private const FINE_FREE_DAYS = 14;
    private const FINE_PER_DAY = 5;

    public function borrowBook(int $bid, int $mid, int $quantity = 1): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            // Check if member would exceed maximum copies after borrowing
            $currentCount = $this->getActiveBorrowCount($bid, $mid);
            if (($currentCount + $quantity) > self::MAX_COPIES_PER_BOOK) {
                $this->db->rollBack();
                return false;
            }

            // Check if enough copies are available
            if (!$this->isAvailable($bid, $quantity)) {
                $this->db->rollBack();
                return false;
            }

            if (($this->borrowedThisMonth($mid) + $quantity) > self::MONTHLY_BORROW_LIMIT) {
                $this->db->rollBack();
                return false;
            }

            $stmt = $this->db->prepare(
                'SELECT Cid FROM Copies WHERE Bid = :bid AND Status = "Available" ORDER BY Cid LIMIT ' . $quantity
            );
            $stmt->execute([':bid' => $bid]);
            $copies = $stmt->fetchAll();

            if (count($copies) < $quantity) {
                $this->db->rollBack();
                return false;
            }

            $insert = $this->db->prepare(
                'INSERT INTO Borrows (Cid, Mid, Bdate, Fine, FineStatus) VALUES (:cid, :mid, CURDATE(), 0, "NA")
                 ON DUPLICATE KEY UPDATE Bdate = VALUES(Bdate), Fine = VALUES(Fine), FineStatus = VALUES(FineStatus)'
            );
            $update = $this->db->prepare('UPDATE Copies SET Status = "Rented" WHERE Cid = :cid');

            foreach ($copies as $copy) {
                $cid = (int) $copy['Cid'];
                $insert->execute([':cid' => $cid, ':mid' => $mid]);
                $update->execute([':cid' => $cid]);
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function getActiveBorrowCount(int $bid, int $mid): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM Borrows br
             INNER JOIN Copies c ON c.Cid = br.Cid
             WHERE br.Mid = :mid AND c.Bid = :bid AND c.Status = "Rented"'
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
                    WHERE br.Mid = :mid AND c.Status = "Rented"
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

    public function returnBookCopies(int $bid, int $mid, int $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                'SELECT c.Cid
                 FROM Borrows br
                 INNER JOIN Copies c ON c.Cid = br.Cid
                 WHERE br.Mid = :mid AND c.Bid = :bid AND c.Status = "Rented"
                 ORDER BY c.Cid
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

            $update = $this->db->prepare('UPDATE Copies SET Status = "Available" WHERE Cid = :cid');

            foreach ($copies as $copy) {
                $cid = (int) $copy['Cid'];
                $update->execute([':cid' => $cid]);
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
                                    COUNT(DISTINCT c.Cid) AS Copies,
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
                WHERE c.Status = "Rented"
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
                    WHERE c.Status = "Rented"
                    GROUP BY br.Mid, c.Bid
                ) agg
                INNER JOIN Members m ON m.Mid = agg.Mid
                INNER JOIN Books b ON b.Bid = agg.Bid
                WHERE agg.PaidCount > 0 OR agg.Fine > 0
                ORDER BY agg.Fine DESC, agg.Bdate DESC, m.MemName';

        return $this->db->query($sql)->fetchAll();
    }
}
