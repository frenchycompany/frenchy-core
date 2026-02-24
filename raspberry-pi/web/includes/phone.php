<?php
function normalize_phone(string $raw, string $defaultCountry = 'FR'): string {
    // 1) garder + et les chiffres
    $p = preg_replace('/[^\d+]/', '', $raw ?? '');
    if ($p === '' ) return '';

    // 2) Si déjà en +E.164 -> OK (commence par + et >= 8 chiffres)
    if ($p[0] === '+' && strlen(preg_replace('/\D/','', $p)) >= 8) {
        return $p;
    }

    // 3) Naïf FR : si commence par 0 et 10 chiffres -> +33 sans le 0
    // (ajuste selon tes pays / règles)
    $digits = preg_replace('/\D/','', $p);
    if ($defaultCountry === 'FR') {
        if (preg_match('/^0\d{9}$/', $digits)) {
            return '+33' . substr($digits, 1);
        }
        // si déjà 9–12 chiffres sans +, tente +33
        if (preg_match('/^\d{9,12}$/', $digits)) {
            return '+33' . $digits;
        }
    }
    // fallback : préfixer + si ce sont des chiffres
    return '+' . $digits;
}
