<?php
namespace MNS\NavasanPlus\Services;

if (!defined('ABSPATH')) {
  exit();
}

use MNS\NavasanPlus\DB;

final class PriceCalculator {
  private static ?self $instance = null;
  public static function instance(): self {
    return self::$instance ??= new self();
  }

  public function calculate($product_or_id) {
    $product = is_numeric($product_or_id) ? wc_get_product((int) $product_or_id) : $product_or_id;
    if (!$product instanceof \WC_Product) {
      return null;
    }

    $db = DB::instance();
    $active = $product->get_meta($db->full_meta_key('active'), true);
    $active = is_string($active) ? $active === 'yes' || $active === '1' : (bool) $active;
    if (!$active) {
      return null;
    }

    $dep = (string) $product->get_meta($db->full_meta_key('dependence_type'), true);
    $dep = $dep !== '' ? strtolower($dep) : 'simple';

    if (in_array($dep, ['advanced', 'formula'], true)) {
      return $this->calc_advanced($product);
    }

    $price = $this->calc_simple($product);
    if ($price === null) {
      return null;
    }
    // In simple mode we don't have internal discounts
    return [
      'price' => (float) $price,
      'price_before' => (float) $price,
      'profit' => 0.0,
      'charge' => 0.0,
    ];
  }

  /** Determine component role (profit/charge/none) */
  private function detect_component_role(array $c): string {
    if (!empty($c['role'])) {
      $r = strtolower((string) $c['role']);
      return in_array($r, ['profit', 'charge'], true) ? $r : 'none';
    }
    $hay = strtolower(
      (string) ($c['name'] ?? '') . ' ' . (string) ($c['text'] ?? ($c['expression'] ?? '')),
    );
    if (strpos($hay, 'profit') !== false || strpos($hay, 'profit') !== false) {
      return 'profit';
    }
    if (strpos($hay, 'charge') !== false || strpos($hay, 'charge') !== false) {
      return 'charge';
    }
    return 'none';
  }

  private function calc_advanced(\WC_Product $product): ?array {
    $db = DB::instance();
    $fid = (int) $product->get_meta($db->full_meta_key('formula_id'), true);
    if ($fid <= 0) {
      return null;
    }

    $vars_meta = (array) get_post_meta($fid, $db->full_meta_key('formula_variables'), true);
    $expr = (string) get_post_meta($fid, $db->full_meta_key('formula_expression'), true);
    $components = (array) get_post_meta($fid, $db->full_meta_key('formula_components'), true);

    // overrides: [fid][code]['regular']
    $over_all = (array) $product->get_meta($db->full_meta_key('formula_variables'), true);
    $over = (array) ($over_all[$fid] ?? []);

    // 1) Build usable value for engine: code => unit × value
    $vars_for_engine = [];
    $var_roles_for_fallback = [];
    foreach ($vars_meta as $code => $row) {
      $code = sanitize_key($code);
      if ($code === '') {
        continue;
      }

      $role = (string) ($row['role'] ?? 'none');
      if (!in_array($role, ['none', 'profit', 'charge'], true)) {
        $role = 'none';
      }
      $var_roles_for_fallback[$code] = $role;

      $cid = (int) ($row['currency_id'] ?? 0);
      $type = (string) ($row['type'] ?? '') === 'currency' || $cid > 0 ? 'currency' : 'custom';

      // --- Determine unit
      if ($type === 'currency') {
        $unit = $this->get_currency_rate($cid);
      } else {
        $uRaw = $row['unit'] ?? '';
        $unit = $uRaw === '' || $uRaw === null ? 1.0 : (float) $uRaw;
      }

      // --- Override value from product itself
      $ov = $over[$code]['regular'] ?? null;
      if ($ov === '') {
        $ov = null;
      }
      $val = $ov !== null ? (float) $ov : (float) ($row['value'] ?? 0);

      $vars_for_engine[$code] = (float) $unit * (float) $val;
    }

    $engine = new \MNS\NavasanPlus\Services\FormulaEngine();

    // 2) If we have role-based components → we sum profit/charge directly from components
    $has_role_component = false;
    $sum_profit_base = 0.0;
    $sum_charge_base = 0.0;
    $sum_other = 0.0;

    foreach ($components as $c) {
      $texpr = (string) ($c['text'] ?? ($c['expression'] ?? ''));
      if (trim($texpr) === '') {
        continue;
      }

      $val = 0.0;
      try {
        $val = (float) $engine->evaluate($texpr, $vars_for_engine);
      } catch (\Throwable $e) {
        $val = 0.0;
      }

      $role = $this->detect_component_role($c);
      if ($role === 'profit') {
        $sum_profit_base += $val;
        $has_role_component = true;
      } elseif ($role === 'charge') {
        $sum_charge_base += $val;
        $has_role_component = true;
      } else {
        $sum_other += $val;
      }
    }

    if ($has_role_component) {
      // Apply discount to final profit/charge amounts
      $profit_after = $sum_profit_base;
      $charge_after = $sum_charge_base;
      if (class_exists('\MNS\NavasanPlus\Services\DiscountService')) {
        [$profit_after, $charge_after] = \MNS\NavasanPlus\Services\DiscountService::apply(
          (float) $sum_profit_base,
          (float) $sum_charge_base,
          (int) $product->get_id(),
        );
      }

      $final_before = (float) $sum_other + (float) $sum_profit_base + (float) $sum_charge_base;
      $final_after = (float) $sum_other + (float) $profit_after + (float) $charge_after;

      return [
        'price_before' => max(0.0, $final_before),
        'price' => max(0.0, $final_after),
        'profit' => max(0.0, (float) $profit_after),
        'charge' => max(0.0, (float) $charge_after),
      ];
    }

    // 3) Otherwise: variable roles + scale (fallback)
    $sum_profit_base = 0.0;
    $sum_charge_base = 0.0;
    foreach ($vars_for_engine as $code => $base) {
      $role = $var_roles_for_fallback[$code] ?? 'none';
      if ($role === 'profit') {
        $sum_profit_base += (float) $base;
      }
      if ($role === 'charge') {
        $sum_charge_base += (float) $base;
      }
    }

    // After discount
    $profit_after = $sum_profit_base;
    $charge_after = $sum_charge_base;
    if (class_exists('\MNS\NavasanPlus\Services\DiscountService')) {
      [$profit_after, $charge_after] = \MNS\NavasanPlus\Services\DiscountService::apply(
        (float) $sum_profit_base,
        (float) $sum_charge_base,
        (int) $product->get_id(),
      );
    }

    $scale_profit = $sum_profit_base > 0 ? $profit_after / $sum_profit_base : 1.0;
    $scale_charge = $sum_charge_base > 0 ? $charge_after / $sum_charge_base : 1.0;

    $vars_scaled = [];
    foreach ($vars_for_engine as $code => $base) {
      $role = $var_roles_for_fallback[$code] ?? 'none';
      $k = $role === 'profit' ? $scale_profit : ($role === 'charge' ? $scale_charge : 1.0);
      $vars_scaled[$code] = max(0.0, (float) $base * (float) $k);
    }

    $final_before = null;
    $final_after = null;

    // Before discount ← with raw values
    try {
      if (trim($expr) !== '') {
        $tmp = $engine->evaluate($expr, $vars_for_engine);
        if (is_numeric($tmp)) {
          $final_before = (float) $tmp;
        }
      } elseif (!empty($components)) {
        $sum = 0.0;
        foreach ($components as $c) {
          $texpr = (string) ($c['text'] ?? ($c['expression'] ?? ''));
          if (trim($texpr) === '') {
            continue;
          }
          $val = $engine->evaluate($texpr, $vars_for_engine);
          if (is_numeric($val)) {
            $sum += (float) $val;
          }
        }
        $final_before = $sum;
      }
    } catch (\Throwable $e) {
    }

    // After discount ← with scaled values
    try {
      if (trim($expr) !== '') {
        $tmp = $engine->evaluate($expr, $vars_scaled);
        if (is_numeric($tmp)) {
          $final_after = (float) $tmp;
        }
      } elseif (!empty($components)) {
        $sum = 0.0;
        foreach ($components as $c) {
          $texpr = (string) ($c['text'] ?? ($c['expression'] ?? ''));
          if (trim($texpr) === '') {
            continue;
          }
          $val = $engine->evaluate($texpr, $vars_scaled);
          if (is_numeric($val)) {
            $sum += (float) $val;
          }
        }
        $final_after = $sum;
      }
    } catch (\Throwable $e) {
    }

    if ($final_before === null || $final_after === null) {
      return null;
    }

    return [
      'price_before' => max(0.0, (float) $final_before),
      'price' => max(0.0, (float) $final_after),
      'profit' => max(0.0, (float) $profit_after),
      'charge' => max(0.0, (float) $charge_after),
    ];
  }

  private function get_currency_rate(int $currency_id): float {
    if ($currency_id <= 0) {
      return 0.0;
    }
    $db = DB::instance();
    $rate = get_post_meta($currency_id, $db->full_meta_key('currency_value'), true);
    return $rate === '' ? 0.0 : (float) $rate;
  }

  private function calc_simple(\WC_Product $product): ?float {
    $db = DB::instance();
    $cid = (int) $product->get_meta($db->full_meta_key('currency_id'), true);
    if ($cid <= 0) {
      return null;
    }

    $rate = $this->get_currency_rate($cid);
    if ($rate <= 0) {
      return null;
    }

    $profit_type =
      (string) $product->get_meta($db->full_meta_key('profit_type'), true) ?: 'percent';
    $profit_value = (float) $product->get_meta($db->full_meta_key('profit_value'), true);

    $base = (float) $rate;
    $price =
      $profit_type === 'fixed' ? $base + $profit_value : $base * (1.0 + $profit_value / 100.0);

    $round_type = (string) $product->get_meta($db->full_meta_key('rounding_type'), true) ?: 'none';
    $round_side = (string) $product->get_meta($db->full_meta_key('rounding_side'), true) ?: 'close';
    $round_value = (float) $product->get_meta($db->full_meta_key('rounding_value'), true);
    if ($round_type !== 'none' && $round_value > 0) {
      $price = $this->round_price($price, $round_type, $round_side, $round_value);
    }

    $ceil = $product->get_meta($db->full_meta_key('ceil_price'), true);
    $floor = $product->get_meta($db->full_meta_key('floor_price'), true);
    if ($ceil !== '') {
      $price = min((float) $ceil, $price);
    }
    if ($floor !== '') {
      $price = max((float) $floor, $price);
    }

    return $price;
  }

  private function round_price(float $price, string $type, string $side, float $value): float {
    switch ($type) {
      case 'zero':
        $m = max(1.0, $value);
        $d = $price / $m;
        if ($side === 'up') {
          $d = ceil($d);
        } elseif ($side === 'down') {
          $d = floor($d);
        } else {
          $d = round($d);
        }
        return $d * $m;
      case 'integer':
        if ($side === 'up') {
          return ceil($price);
        }
        if ($side === 'down') {
          return floor($price);
        }
        return round($price);
    }
    return $price;
  }
}
