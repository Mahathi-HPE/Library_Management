<?php
declare(strict_types=1);

class Book extends Model
{
    public function availableBooks(?string $search = null): array
    {
        $like = $search ? '%' . $search . '%' : null;
        $stmt = $this->db->prepare(
            'SELECT b.Bid, b.Title, b.Price, b.PubDate, GROUP_CONCAT(DISTINCT a.AuthName SEPARATOR ", ") AS AuthName,
                    COUNT(DISTINCT CASE WHEN c.Status = "Available" THEN c.Cid END) AS AvailableCopies
             FROM Books b
             LEFT JOIN BookAuthor ba ON ba.Bid = b.Bid
             LEFT JOIN Author a ON a.Aid = ba.Aid
             LEFT JOIN Copies c ON c.Bid = b.Bid
             WHERE :search IS NULL OR b.Title LIKE :like OR a.AuthName LIKE :like
             GROUP BY b.Bid, b.Title, b.Price, b.PubDate
             HAVING AvailableCopies > 0
             ORDER BY b.Title'
        );
        $stmt->execute([':search' => $like, ':like' => $like]);
        return $stmt->fetchAll();
    }

    public function addBookWithAuthorsAndCopy(string $title, array $authors, float $price, string $pubDate, int $copies): bool
    {
        if (empty($authors) || $copies <= 0) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            $this->db->prepare('INSERT INTO Books (Title, PubDate, Price) VALUES (:title, :pubDate, :price)')->execute([':title' => $title, ':pubDate' => $pubDate, ':price' => $price]);
            $bid = (int) $this->db->lastInsertId();

            $linkStmt = $this->db->prepare('INSERT INTO BookAuthor (Bid, Aid) VALUES (:bid, :aid)');
            foreach ($authors as $author) {
                $name = trim((string) ($author['name'] ?? ''));
                $location = trim((string) ($author['location'] ?? 'Unknown'));
                $email = trim((string) ($author['email'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $aid = $this->ensureAuthor($name, $location === '' ? 'Unknown' : $location, $email);
                $linkStmt->execute([':bid' => $bid, ':aid' => $aid]);
            }

            $copyInsert = $this->db->prepare('INSERT INTO Copies (Bid, Status) VALUES (:bid, "Available")');
            for ($index = 0; $index < $copies; $index++) {
                $copyInsert->execute([':bid' => $bid]);
            }
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    private function ensureAuthor(string $authorName, string $authorLocation, string $authorEmail = ''): int
    {
        $stmt = $this->db->prepare('SELECT Aid, AuthLoc, AuthEmail FROM Author WHERE AuthName = :name LIMIT 1');
        $stmt->execute([':name' => $authorName]);
        $author = $stmt->fetch();

        $resolvedEmail = $authorEmail !== ''
            ? $authorEmail
            : strtolower(str_replace(' ', '.', $authorName)) . '@example.com';

        if ($author) {
            if (!empty($authorLocation) && ($author['AuthLoc'] === null || $author['AuthLoc'] === '' || $author['AuthLoc'] === 'Unknown')) {
                $this->db->prepare('UPDATE Author SET AuthLoc = :loc WHERE Aid = :aid')->execute([
                    ':loc' => $authorLocation,
                    ':aid' => (int) $author['Aid'],
                ]);
            }

            if ($authorEmail !== '' && ($author['AuthEmail'] === null || $author['AuthEmail'] === '' || str_ends_with((string) $author['AuthEmail'], '@example.com'))) {
                $this->db->prepare('UPDATE Author SET AuthEmail = :email WHERE Aid = :aid')->execute([
                    ':email' => $authorEmail,
                    ':aid' => (int) $author['Aid'],
                ]);
            }

            return (int) $author['Aid'];
        }

        $this->db->prepare('INSERT INTO Author (AuthLoc, AuthEmail, AuthName) VALUES (:loc, :email, :name)')->execute([
            ':loc' => $authorLocation === '' ? 'Unknown' : $authorLocation,
            ':email' => $resolvedEmail,
            ':name' => $authorName,
        ]);

        return (int) $this->db->lastInsertId();
    }
}
