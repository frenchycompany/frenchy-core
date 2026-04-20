<?php
require_once __DIR__ . '/db.php';

class PricingEngine {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getBasePrice(int $logementId): array {
        $stmt = $this->pdo->prepare("
            SELECT default_price, weekend_price, min_price, max_price
            FROM superhote_config
            WHERE logement_id = ? AND is_active = 1
        ");
        $stmt->execute([$logementId]);
        return $stmt->fetch() ?: ['default_price' => 0, 'weekend_price' => 0, 'min_price' => 0, 'max_price' => 0];
    }

    public function getSeasons(): array {
        $stmt = $this->pdo->query("
            SELECT nom, date_debut, date_fin, type_saison, majoration_pourcent, reduction_pourcent, priorite
            FROM superhote_seasons
            WHERE is_active = 1
            ORDER BY priorite DESC
        ");
        return $stmt->fetchAll();
    }

    public function getHolidays(): array {
        $stmt = $this->pdo->query("
            SELECT nom, date_ferie, is_recurring, majoration_pourcent, jours_autour
            FROM superhote_holidays
            WHERE is_active = 1
        ");
        return $stmt->fetchAll();
    }

    public function getPricingRules(int $logementId): array {
        $stmt = $this->pdo->prepare("
            SELECT name, rule_type, price, price_modifier, priority, conditions, weekdays, valid_from, valid_until
            FROM superhote_pricing_rules
            WHERE logement_id = ? AND is_active = 1
            ORDER BY priority DESC
        ");
        $stmt->execute([$logementId]);
        return $stmt->fetchAll();
    }

    public function isWeekend(string $date): bool {
        $dow = (int) date('N', strtotime($date));
        return $dow >= 6;
    }

    public function getSeasonModifier(string $date, array $seasons): float {
        $md = date('m-d', strtotime($date));

        foreach ($seasons as $s) {
            $start = date('m-d', strtotime($s['date_debut']));
            $end = date('m-d', strtotime($s['date_fin']));

            $inRange = ($start <= $end)
                ? ($md >= $start && $md <= $end)
                : ($md >= $start || $md <= $end);

            if ($inRange) {
                if ($s['type_saison'] === 'haute') return (float) $s['majoration_pourcent'];
                if ($s['type_saison'] === 'basse') return -(float) $s['reduction_pourcent'];
            }
        }
        return 0.0;
    }

    public function getHolidayModifier(string $date, array $holidays): float {
        $modifier = 0.0;
        foreach ($holidays as $h) {
            $holidayMd = date('m-d', strtotime($h['date_ferie']));
            $dateMd = date('m-d', strtotime($date));
            $around = (int) $h['jours_autour'];

            if ($around === 0) {
                if ($dateMd === $holidayMd) {
                    $modifier = max($modifier, (float) $h['majoration_pourcent']);
                }
            } else {
                $target = strtotime(date('Y') . '-' . $holidayMd);
                $current = strtotime($date);
                $diff = abs($current - $target) / 86400;
                if ($diff <= $around) {
                    $modifier = max($modifier, (float) $h['majoration_pourcent']);
                }
            }
        }
        return $modifier;
    }

    public function calculateNightPrice(int $logementId, string $date, ?array $baseConfig = null): array {
        if (!$baseConfig) $baseConfig = $this->getBasePrice($logementId);

        $basePrice = (float) $baseConfig['default_price'];
        if ($this->isWeekend($date) && $baseConfig['weekend_price'] > 0) {
            $basePrice = (float) $baseConfig['weekend_price'];
        }

        $seasons = $this->getSeasons();
        $holidays = $this->getHolidays();

        $seasonMod = $this->getSeasonModifier($date, $seasons);
        $holidayMod = $this->getHolidayModifier($date, $holidays);

        $finalPrice = $basePrice * (1 + $seasonMod / 100) * (1 + $holidayMod / 100);

        $min = (float) $baseConfig['min_price'];
        $max = (float) $baseConfig['max_price'];
        if ($min > 0) $finalPrice = max($finalPrice, $min);
        if ($max > 0) $finalPrice = min($finalPrice, $max);

        return [
            'date' => $date,
            'base_price' => $basePrice,
            'season_modifier' => $seasonMod,
            'holiday_modifier' => $holidayMod,
            'final_price' => round($finalPrice, 2),
            'is_weekend' => $this->isWeekend($date),
        ];
    }

    public function calculatePeriod(int $logementId, string $checkin, string $checkout): array {
        $baseConfig = $this->getBasePrice($logementId);
        $nights = [];
        $total = 0;

        $current = new DateTime($checkin);
        $end = new DateTime($checkout);

        while ($current < $end) {
            $nightInfo = $this->calculateNightPrice($logementId, $current->format('Y-m-d'), $baseConfig);
            $nights[] = $nightInfo;
            $total += $nightInfo['final_price'];
            $current->modify('+1 day');
        }

        return [
            'checkin' => $checkin,
            'checkout' => $checkout,
            'nb_nights' => count($nights),
            'nights' => $nights,
            'total' => round($total, 2),
            'avg_per_night' => count($nights) > 0 ? round($total / count($nights), 2) : 0,
        ];
    }

    public function calculateMultiPeriods(int $logementId, array $periods): array {
        $results = [];
        $grandTotal = 0;
        $totalNights = 0;

        foreach ($periods as $p) {
            $periodResult = $this->calculatePeriod($logementId, $p['checkin'], $p['checkout']);
            $results[] = $periodResult;
            $grandTotal += $periodResult['total'];
            $totalNights += $periodResult['nb_nights'];
        }

        $discount = 0;
        if ($totalNights >= 28) $discount = 15;
        elseif ($totalNights >= 14) $discount = 10;
        elseif ($totalNights >= 7) $discount = 5;

        $discountAmount = round($grandTotal * $discount / 100, 2);

        return [
            'periods' => $results,
            'total_nights' => $totalNights,
            'subtotal' => $grandTotal,
            'long_stay_discount_percent' => $discount,
            'long_stay_discount_amount' => $discountAmount,
            'total' => round($grandTotal - $discountAmount, 2),
        ];
    }
}
