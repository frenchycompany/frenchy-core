<?php

class BookingMailer {
    private PDO $pdo;
    private string $fromEmail;
    private string $fromName;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->fromName = 'FrenchyConciergerie';
        $this->fromEmail = 'noreply@frenchyconciergerie.fr';

        try {
            $stmt = $pdo->query("SELECT setting_value FROM bot_settings WHERE setting_key = 'company_email'");
            $row = $stmt->fetch();
            if ($row && $row['setting_value']) $this->fromEmail = $row['setting_value'];
        } catch (Exception $e) {}
    }

    public function sendConfirmation(array $booking, ?string $invoicePath = null): bool {
        $pricing = is_string($booking['pricing_json']) ? json_decode($booking['pricing_json'], true) : $booking['pricing_json'];
        $proInfo = null;
        if ($booking['is_pro'] && $booking['pro_info']) {
            $proInfo = is_string($booking['pro_info']) ? json_decode($booking['pro_info'], true) : $booking['pro_info'];
        }

        $periodsHtml = '';
        foreach ($pricing['periods'] as $p) {
            $checkin = date('d/m/Y', strtotime($p['checkin']));
            $checkout = date('d/m/Y', strtotime($p['checkout']));
            $periodsHtml .= "<tr>
                <td style='padding:8px;border-bottom:1px solid #eee'>$checkin — $checkout</td>
                <td style='padding:8px;border-bottom:1px solid #eee;text-align:center'>{$p['nb_nights']} nuit(s)</td>
                <td style='padding:8px;border-bottom:1px solid #eee;text-align:right'>" . number_format($p['total'], 2, ',', ' ') . " &euro;</td>
            </tr>";
        }

        $discountHtml = '';
        if ($pricing['long_stay_discount_amount'] > 0) {
            $discountHtml = "<tr>
                <td colspan='2' style='padding:8px;color:#16a34a'>Remise long s&eacute;jour (-{$pricing['long_stay_discount_percent']}%)</td>
                <td style='padding:8px;text-align:right;color:#16a34a'>-" . number_format($pricing['long_stay_discount_amount'], 2, ',', ' ') . " &euro;</td>
            </tr>";
        }

        $statusLabel = $booking['status'] === 'paid' ? 'Confirm&eacute;e et pay&eacute;e' : 'En attente de paiement';
        $statusColor = $booking['status'] === 'paid' ? '#16a34a' : '#f59e0b';

        $logementName = 'Votre h&eacute;bergement';
        try {
            $stmt = $this->pdo->prepare("SELECT nom_du_logement FROM liste_logements WHERE id = ?");
            $stmt->execute([$booking['logement_id']]);
            $l = $stmt->fetch();
            if ($l) $logementName = htmlspecialchars($l['nom_du_logement']);
        } catch (Exception $e) {}

        $virementHtml = '';
        if ($booking['payment_method'] === 'virement') {
            $virementHtml = "
            <div style='background:#f5f0e0;padding:16px;border-radius:8px;margin:16px 0'>
                <h3 style='color:#8b6914;margin:0 0 8px'>Instructions de virement</h3>
                <p style='margin:4px 0'>Montant : <strong>" . number_format($pricing['total'], 2, ',', ' ') . " &euro;</strong></p>
                <p style='margin:4px 0'>R&eacute;f&eacute;rence : <strong>{$booking['booking_ref']}</strong></p>
                <p style='margin:4px 0;font-size:13px;color:#666'>Merci d'effectuer le virement sous 48h.</p>
            </div>";
        }

        $invoiceLinkHtml = '';
        if ($booking['invoice_number']) {
            $invoiceLinkHtml = "<p style='text-align:center;margin:16px 0'>
                <a href='https://frenchyconciergerie.fr/frenchysite/booking/api/invoice.php?ref={$booking['booking_ref']}&email={$booking['guest_email']}'
                   style='background:#2d5016;color:white;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600'>
                    T&eacute;l&eacute;charger la facture
                </a>
            </p>";
        }

        $html = "
        <div style='font-family:Inter,Helvetica,Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff'>
            <div style='background:#2d5016;padding:24px;text-align:center'>
                <h1 style='color:#fff;margin:0;font-size:24px'>FrenchyConciergerie</h1>
            </div>
            <div style='padding:24px'>
                <h2 style='color:#2d5016;margin:0 0 8px'>R&eacute;servation {$statusLabel}</h2>
                <p style='color:#666;margin:0 0 16px'>R&eacute;f&eacute;rence : <strong>{$booking['booking_ref']}</strong></p>

                <div style='background:#f9fafb;padding:16px;border-radius:8px;margin:16px 0'>
                    <h3 style='margin:0 0 4px;color:#2d5016'>$logementName</h3>
                    <span style='display:inline-block;padding:3px 10px;background:$statusColor;color:#fff;border-radius:12px;font-size:12px'>$statusLabel</span>
                </div>

                <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                    <thead>
                        <tr style='background:#f3f4f6'>
                            <th style='padding:8px;text-align:left'>P&eacute;riode</th>
                            <th style='padding:8px;text-align:center'>Nuits</th>
                            <th style='padding:8px;text-align:right'>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        $periodsHtml
                        $discountHtml
                        <tr style='font-weight:700;font-size:16px'>
                            <td colspan='2' style='padding:12px 8px'>Total</td>
                            <td style='padding:12px 8px;text-align:right;color:#2d5016'>" . number_format($pricing['total'], 2, ',', ' ') . " &euro;</td>
                        </tr>
                    </tbody>
                </table>

                $virementHtml
                $invoiceLinkHtml

                <p style='color:#666;font-size:13px;margin-top:24px'>
                    Merci pour votre r&eacute;servation. N'h&eacute;sitez pas &agrave; nous contacter pour toute question.
                </p>
            </div>
            <div style='background:#f3f4f6;padding:16px;text-align:center;font-size:12px;color:#999'>
                FrenchyConciergerie &mdash; {$this->fromEmail}
            </div>
        </div>";

        $subject = "Réservation {$booking['booking_ref']} — $logementName";

        $boundary = md5(time());
        $headers = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "Reply-To: {$this->fromEmail}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";

        if ($invoicePath && file_exists($invoicePath)) {
            $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

            $body = "--$boundary\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $body .= $html . "\r\n";

            $body .= "--$boundary\r\n";
            $body .= "Content-Type: application/pdf; name=\"" . basename($invoicePath) . "\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"" . basename($invoicePath) . "\"\r\n\r\n";
            $body .= chunk_split(base64_encode(file_get_contents($invoicePath))) . "\r\n";
            $body .= "--$boundary--\r\n";
        } else {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body = $html;
        }

        return mail($booking['guest_email'], $subject, $body, $headers);
    }

    public function notifyAdmin(array $booking): void {
        $pricing = is_string($booking['pricing_json']) ? json_decode($booking['pricing_json'], true) : $booking['pricing_json'];

        $subject = "[NOUVELLE RESA] {$booking['booking_ref']} - {$booking['guest_name']} - " . number_format($pricing['total'], 2) . "€";
        $body = "Nouvelle réservation directe\n\n";
        $body .= "Ref: {$booking['booking_ref']}\n";
        $body .= "Client: {$booking['guest_name']} ({$booking['guest_email']})\n";
        $body .= "Pro: " . ($booking['is_pro'] ? 'Oui' : 'Non') . "\n";
        $body .= "Périodes: " . count($pricing['periods']) . "\n";
        $body .= "Nuits: {$pricing['total_nights']}\n";
        $body .= "Total: " . number_format($pricing['total'], 2) . "€\n";
        $body .= "Paiement: {$booking['payment_method']}\n";
        $body .= "Statut: {$booking['status']}\n";

        $headers = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        try {
            $stmt = $this->pdo->query("SELECT setting_value FROM bot_settings WHERE setting_key = 'admin_email'");
            $row = $stmt->fetch();
            if ($row && $row['setting_value']) {
                mail($row['setting_value'], $subject, $body, $headers);
            }
        } catch (Exception $e) {}
    }
}
