<?php
/**
 * Global Formatters & Unit Converters
 */

/**
 * Intelligent unit formatter and scaler.
 * Handles bidirectional conversion (e.g., 0.5kg -> 500g, 1500g -> 1.5kg).
 * Normalizes casing for consistency.
 * 
 * @param float  $qty  The quantity to format
 * @param string $unit The unit string
 * @return string      Formatted string (e.g., "500 g")
 */
function formatUnit(float $qty, string $unit): string {
    $u = trim(strtolower($unit));
    $val = $qty;
    $finalUnit = $u;

    // ── Weights (kg <-> g) ──
    if ($u === 'kg' || $u === 'kilogram' || $u === 'kilograms') {
        if ($val < 1.0 && $val > 0) {
            $val *= 1000;
            $finalUnit = 'g';
        } else {
            $finalUnit = 'kg';
        }
    } elseif ($u === 'g' || $u === 'gram' || $u === 'grams') {
        if ($val >= 1000) {
            $val /= 1000;
            $finalUnit = 'kg';
        } else {
            $finalUnit = 'g';
        }
    }

    // ── Volumes (L <-> ml) ──
    elseif ($u === 'l' || $u === 'liter' || $u === 'liters' || $u === 'litre') {
        if ($val < 1.0 && $val > 0) {
            $val *= 1000;
            $finalUnit = 'ml';
        } else {
            $finalUnit = 'L'; // Keep L uppercase as per SI
        }
    } elseif ($u === 'ml' || $u === 'milliliter' || $u === 'milliliters') {
        if ($val >= 1000) {
            $val /= 1000;
            $finalUnit = 'L';
        } else {
            $finalUnit = 'ml';
        }
    }

    // ── Counts / Packaging Normalization ──
    elseif (in_array($u, ['pc', 'pcs', 'piece', 'pieces'])) {
        $finalUnit = 'pcs';
    } elseif (in_array($u, ['pack', 'packs', 'pck', 'pks'])) {
        $finalUnit = 'packs';
    } elseif (in_array($u, ['can', 'cans', 'cn'])) {
        $finalUnit = 'cans';
    } elseif (in_array($u, ['jar', 'jars'])) {
        $finalUnit = 'jars';
    } elseif (in_array($u, ['head', 'heads'])) {
        $finalUnit = 'heads';
    } elseif (in_array($u, ['bundle', 'bundles'])) {
        $finalUnit = 'bundles';
    }

    // Standardize casing for others
    else {
        $finalUnit = $u;
    }

    // Remove trailing zeros for a cleaner look
    $formattedVal = round($val, 3);
    if ($formattedVal == (int)$formattedVal) {
        $formattedVal = (int)$formattedVal;
    }

    return $formattedVal . ' ' . $finalUnit;
}
