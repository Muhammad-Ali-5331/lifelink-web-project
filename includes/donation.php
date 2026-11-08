<?php
// The most important code in the project: the confirm-donation TRANSACTION.
// Either every step succeeds and is saved together (COMMIT), or nothing is saved (ROLLBACK).

require_once __DIR__ . '/functions.php';

/** @return array [bool success, string message] */
function confirm_donation(PDO $pdo, int $requestId, int $responseId, int $requesterId): array
{
    try {
        $pdo->beginTransaction();

        // 1. LOCK the request row. A second requester/tab trying the same thing
        //    must wait here until we commit, so the last unit cannot be over-filled.
        $st = $pdo->prepare('SELECT * FROM blood_requests
                             WHERE request_id = ? AND requester_id = ? FOR UPDATE');
        $st->execute([$requestId, $requesterId]);
        $req = $st->fetch();
        if (!$req) {
            throw new RuntimeException('Request not found.');
        }

        // 2. CHECK the request is still open and has units left.
        if ($req['status'] !== 'open' || $req['units_fulfilled'] >= $req['units_needed']) {
            throw new RuntimeException('This request is no longer open.');
        }

        // The offer must belong to this request and must have been accepted.
        $st = $pdo->prepare('SELECT * FROM donor_responses
                             WHERE response_id = ? AND request_id = ? FOR UPDATE');
        $st->execute([$responseId, $requestId]);
        $resp = $st->fetch();
        if (!$resp || $resp['status'] !== 'accepted') {
            throw new RuntimeException('Only an accepted offer can be confirmed.');
        }

        // Safety: the donor must still be allowed to donate (90-day rule).
        $st = $pdo->prepare('SELECT last_donation_date,
                                    DATEDIFF(CURDATE(), last_donation_date) AS days_since
                             FROM donors WHERE user_id = ? FOR UPDATE');
        $st->execute([$resp['donor_id']]);
        $donor = $st->fetch();
        if ($donor['last_donation_date'] !== null && $donor['days_since'] < WAIT_DAYS) {
            throw new RuntimeException('This donor donated less than ' . WAIT_DAYS . ' days ago.');
        }

        // 3. INSERT the donation + mark the response completed.
        $pdo->prepare('INSERT INTO donations (response_id, donated_on) VALUES (?, CURDATE())')
            ->execute([$responseId]);
        $pdo->prepare("UPDATE donor_responses SET status = 'completed' WHERE response_id = ?")
            ->execute([$responseId]);

        // 4. UPDATE units; close the request when all units are received.
        $filled    = $req['units_fulfilled'] + 1;
        $newStatus = ($filled >= $req['units_needed']) ? 'fulfilled' : 'open';
        $pdo->prepare('UPDATE blood_requests SET units_fulfilled = ?, status = ? WHERE request_id = ?')
            ->execute([$filled, $newStatus, $requestId]);

        // 5. UPDATE the donor's last donation date -> starts their 90-day wait.
        $pdo->prepare('UPDATE donors SET last_donation_date = CURDATE() WHERE user_id = ?')
            ->execute([$resp['donor_id']]);

        // Request finished: decline offers that are no longer needed.
        if ($newStatus === 'fulfilled') {
            $pdo->prepare("UPDATE donor_responses SET status = 'declined'
                           WHERE request_id = ? AND status IN ('offered','accepted')")
                ->execute([$requestId]);
        }

        // 6. COMMIT: everything above is saved at once.
        $pdo->commit();
        return [true, $newStatus === 'fulfilled'
            ? 'Donation confirmed. The request is now fulfilled. Thank you!'
            : 'Donation confirmed. ' . $filled . ' of ' . $req['units_needed'] . ' units received.'];

    } catch (RuntimeException $e) {            // our own "friendly" problems
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        return [false, $e->getMessage()];
    } catch (Throwable $e) {                   // unexpected database errors
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('confirm_donation failed: ' . $e->getMessage());
        return [false, 'Something went wrong. Nothing was saved.'];
    }
}

/**
 * Accept an offer (reveals the donor's phone). Locks the request so we never
 * accept more donors than units needed.
 * @return array [bool success, string message]
 */
function accept_offer(PDO $pdo, int $requestId, int $responseId, int $requesterId): array
{
    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare('SELECT * FROM blood_requests
                             WHERE request_id = ? AND requester_id = ? FOR UPDATE');
        $st->execute([$requestId, $requesterId]);
        $req = $st->fetch();
        if (!$req || $req['status'] !== 'open') {
            throw new RuntimeException('This request is not open.');
        }

        $st = $pdo->prepare("SELECT COUNT(*) FROM donor_responses
                             WHERE request_id = ? AND status = 'accepted'");
        $st->execute([$requestId]);
        if ($st->fetchColumn() + $req['units_fulfilled'] >= $req['units_needed']) {
            throw new RuntimeException('You already accepted enough donors for the units needed.');
        }

        $st = $pdo->prepare("UPDATE donor_responses SET status = 'accepted'
                             WHERE response_id = ? AND request_id = ? AND status = 'offered'");
        $st->execute([$responseId, $requestId]);
        if ($st->rowCount() === 0) {
            throw new RuntimeException('That offer cannot be accepted.');
        }

        $pdo->commit();
        return [true, 'Offer accepted. The donor\'s phone number is now visible.'];
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        return [false, $e->getMessage()];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('accept_offer failed: ' . $e->getMessage());
        return [false, 'Something went wrong. Nothing was saved.'];
    }
}
