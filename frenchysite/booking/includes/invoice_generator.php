<?php
require_once __DIR__ . '/../../../ionos/gestion/lib/fpdf/fpdf.php';

class InvoiceGenerator {
    private PDO $pdo;
    private string $invoiceDir;

    private array $company = [
        'name' => 'FrenchyConciergerie',
        'address' => '',
        'siret' => '',
        'tva' => '',
        'email' => '',
        'phone' => '',
        'iban' => 'FR76 XXXX XXXX XXXX XXXX XXXX XXX',
        'bic' => 'XXXXXXXX',
    ];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->invoiceDir = __DIR__ . '/../invoices';
        if (!is_dir($this->invoiceDir)) mkdir($this->invoiceDir, 0755, true);
        $this->loadCompanyInfo();
    }

    private function loadCompanyInfo(): void {
        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM bot_settings WHERE setting_key LIKE 'company_%'");
            foreach ($stmt->fetchAll() as $row) {
                $key = str_replace('company_', '', $row['setting_key']);
                if (isset($this->company[$key])) {
                    $this->company[$key] = $row['setting_value'];
                }
            }
        } catch (Exception $e) {}
    }

    public function generateInvoiceNumber(): string {
        $year = date('Y');
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) as cnt FROM direct_bookings WHERE invoice_number LIKE ? AND YEAR(created_at) = ?"
        );
        $stmt->execute(["FC-$year-%", $year]);
        $count = (int) $stmt->fetch()['cnt'] + 1;
        return sprintf("FC-%s-%04d", $year, $count);
    }

    public function generate(array $booking): string {
        $invoiceNumber = $booking['invoice_number'] ?? $this->generateInvoiceNumber();
        $pricing = is_string($booking['pricing_json']) ? json_decode($booking['pricing_json'], true) : $booking['pricing_json'];
        $proInfo = null;
        if ($booking['is_pro'] && $booking['pro_info']) {
            $proInfo = is_string($booking['pro_info']) ? json_decode($booking['pro_info'], true) : $booking['pro_info'];
        }

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $this->drawHeader($pdf, $invoiceNumber, $booking);
        $this->drawClientInfo($pdf, $booking, $proInfo);
        $this->drawPropertyInfo($pdf, $booking);
        $this->drawLineItems($pdf, $pricing);
        $this->drawTotals($pdf, $pricing);
        if ($booking['payment_method'] === 'virement') {
            $this->drawBankInfo($pdf, $invoiceNumber, $pricing['total']);
        }
        $this->drawFooter($pdf);

        $filename = "facture_{$invoiceNumber}.pdf";
        $filepath = $this->invoiceDir . '/' . $filename;
        $pdf->Output('F', $filepath);

        $stmt = $this->pdo->prepare(
            "UPDATE direct_bookings SET invoice_number = ?, invoice_pdf_path = ? WHERE id = ?"
        );
        $stmt->execute([$invoiceNumber, $filepath, $booking['id']]);

        return $filepath;
    }

    private function drawHeader(FPDF $pdf, string $invoiceNumber, array $booking): void {
        $pdf->SetFont('Helvetica', 'B', 22);
        $pdf->SetTextColor(45, 80, 22);
        $pdf->Cell(0, 12, $this->utf8('FrenchyConciergerie'), 0, 1);

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(100, 100, 100);
        if ($this->company['address']) $pdf->Cell(0, 4, $this->utf8($this->company['address']), 0, 1);
        if ($this->company['siret']) $pdf->Cell(0, 4, 'SIRET : ' . $this->company['siret'], 0, 1);
        if ($this->company['tva']) $pdf->Cell(0, 4, 'TVA : ' . $this->company['tva'], 0, 1);
        if ($this->company['email']) $pdf->Cell(0, 4, $this->utf8($this->company['email']), 0, 1);

        $pdf->Ln(8);
        $pdf->SetDrawColor(45, 80, 22);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(8);

        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->SetTextColor(30, 30, 30);
        $status = $booking['status'] === 'paid' ? 'FACTURE' : 'FACTURE PROFORMA';
        $pdf->Cell(100, 8, $status, 0, 0);

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 8, $this->utf8("N° $invoiceNumber"), 0, 1, 'R');

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(100, 5, '', 0, 0);
        $pdf->Cell(0, 5, 'Date : ' . date('d/m/Y', strtotime($booking['created_at'])), 0, 1, 'R');

        if ($booking['paid_at']) {
            $pdf->Cell(100, 5, '', 0, 0);
            $pdf->Cell(0, 5, $this->utf8('Payée le : ') . date('d/m/Y', strtotime($booking['paid_at'])), 0, 1, 'R');
        }

        $pdf->Ln(6);
    }

    private function drawClientInfo(FPDF $pdf, array $booking, ?array $proInfo): void {
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->Cell(0, 6, 'Client', 0, 1);

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(60, 60, 60);

        if ($proInfo) {
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->Cell(0, 5, $this->utf8($proInfo['raison_sociale']), 0, 1);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell(0, 4, 'SIRET : ' . $proInfo['siret'], 0, 1);
            if (!empty($proInfo['tva_intracommunautaire'])) {
                $pdf->Cell(0, 4, 'TVA Intra : ' . $proInfo['tva_intracommunautaire'], 0, 1);
            }
            $pdf->Cell(0, 4, $this->utf8($proInfo['adresse_facturation']), 0, 1);
            $pdf->Ln(2);
        }

        $pdf->Cell(0, 5, $this->utf8($booking['guest_name']), 0, 1);
        $pdf->Cell(0, 5, $booking['guest_email'], 0, 1);
        $pdf->Ln(6);
    }

    private function drawPropertyInfo(FPDF $pdf, array $booking): void {
        $stmt = $this->pdo->prepare("SELECT nom_du_logement, adresse FROM liste_logements WHERE id = ?");
        $stmt->execute([$booking['logement_id']]);
        $logement = $stmt->fetch();

        if ($logement) {
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->Cell(0, 6, $this->utf8('Hébergement'), 0, 1);
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->Cell(0, 5, $this->utf8($logement['nom_du_logement']), 0, 1);
            if ($logement['adresse']) {
                $pdf->Cell(0, 5, $this->utf8($logement['adresse']), 0, 1);
            }
            $pdf->Ln(6);
        }
    }

    private function drawLineItems(FPDF $pdf, array $pricing): void {
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetFillColor(45, 80, 22);
        $pdf->SetTextColor(255, 255, 255);

        $pdf->Cell(75, 8, $this->utf8('  Période'), 0, 0, 'L', true);
        $pdf->Cell(30, 8, 'Nuits', 0, 0, 'C', true);
        $pdf->Cell(40, 8, 'Prix moy./nuit', 0, 0, 'C', true);
        $pdf->Cell(45, 8, 'Total HT', 0, 1, 'R', true);

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(30, 30, 30);
        $alt = false;

        foreach ($pricing['periods'] as $p) {
            if ($alt) {
                $pdf->SetFillColor(248, 248, 248);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }

            $checkin = date('d/m/Y', strtotime($p['checkin']));
            $checkout = date('d/m/Y', strtotime($p['checkout']));
            $label = "  $checkin - $checkout";

            $pdf->Cell(75, 7, $this->utf8($label), 0, 0, 'L', true);
            $pdf->Cell(30, 7, $p['nb_nights'], 0, 0, 'C', true);
            $pdf->Cell(40, 7, number_format($p['avg_per_night'], 2, ',', ' ') . $this->utf8(' €'), 0, 0, 'C', true);
            $pdf->Cell(45, 7, number_format($p['total'], 2, ',', ' ') . $this->utf8(' €'), 0, 1, 'R', true);

            $alt = !$alt;
        }

        $pdf->Ln(4);
    }

    private function drawTotals(FPDF $pdf, array $pricing): void {
        $x = 130;

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(60, 60, 60);

        $pdf->SetX($x);
        $pdf->Cell(35, 6, 'Sous-total :', 0, 0, 'R');
        $pdf->Cell(25, 6, number_format($pricing['subtotal'], 2, ',', ' ') . $this->utf8(' €'), 0, 1, 'R');

        if ($pricing['long_stay_discount_amount'] > 0) {
            $pdf->SetTextColor(22, 163, 74);
            $pdf->SetX($x);
            $pdf->Cell(35, 6, $this->utf8('Remise long séjour (-' . $pricing['long_stay_discount_percent'] . '%) :'), 0, 0, 'R');
            $pdf->Cell(25, 6, '-' . number_format($pricing['long_stay_discount_amount'], 2, ',', ' ') . $this->utf8(' €'), 0, 1, 'R');
        }

        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetX($x);
        $pdf->Cell(60, 5, $this->utf8('TVA non applicable, article 293B du CGI'), 0, 1, 'R');

        $pdf->Ln(2);
        $pdf->SetDrawColor(45, 80, 22);
        $pdf->Line($x, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(4);

        $pdf->SetFont('Helvetica', 'B', 14);
        $pdf->SetTextColor(45, 80, 22);
        $pdf->SetX($x);
        $pdf->Cell(35, 8, 'TOTAL :', 0, 0, 'R');
        $pdf->Cell(25, 8, number_format($pricing['total'], 2, ',', ' ') . $this->utf8(' €'), 0, 1, 'R');

        $pdf->Ln(8);
    }

    private function drawBankInfo(FPDF $pdf, string $invoiceNumber, float $total): void {
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(139, 105, 20);
        $pdf->Cell(0, 6, 'Informations de virement', 0, 1);

        $pdf->SetFillColor(245, 240, 224);
        $y = $pdf->GetY();
        $pdf->Rect(10, $y, 190, 28, 'F');

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->Cell(0, 5, $this->utf8("Bénéficiaire : " . $this->company['name']), 0, 1);
        $pdf->Cell(0, 5, 'IBAN : ' . $this->company['iban'], 0, 1);
        $pdf->Cell(0, 5, 'BIC : ' . $this->company['bic'], 0, 1);
        $pdf->Cell(0, 5, $this->utf8("Référence : $invoiceNumber  |  Montant : " . number_format($total, 2, ',', ' ') . " €"), 0, 1);
        $pdf->Cell(0, 5, $this->utf8('Merci d\'effectuer le virement sous 48h.'), 0, 1);

        $pdf->Ln(8);
    }

    private function drawFooter(FPDF $pdf): void {
        $pdf->SetY(-30);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', '', 7);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(0, 4, $this->utf8($this->company['name'] . ' — ' . $this->company['address']), 0, 1, 'C');
        $pdf->Cell(0, 4, $this->utf8('SIRET ' . $this->company['siret'] . ' — TVA non applicable, article 293B du CGI'), 0, 1, 'C');
    }

    private function utf8(string $str): string {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $str);
    }
}
