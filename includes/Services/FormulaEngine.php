<?php
/**
 * Services\FormulaEngine
 *
 * موتور سبک و امن برای ارزیابی عبارات ریاضی با متغیرها.
 * پشتیبانی از:
 *  - عملگرها: + - * / % ^  (توان راست‌چین)، منفیِ یک‌جمله‌ای (u-)
 *  - پرانتز و جداکنندهٔ آرگومان (,)
 *  - متغیرها: [A-Za-z_][A-Za-z0-9_]*  (سازگار با [VAR] هم هست)
 *  - توابع: min(...), max(...), abs(x), ceil(x), floor(x), round(x[, precision])
 *  - shorthand درصد: 5% ⇒ (5/100)
 *
 * بدون eval؛ با Shunting-yard و RPN، سازگار با نوسان قدیمی.
 *
 * File: includes/Services/FormulaEngine.php
 */

namespace MNS\NavasanPlus\Services;

if ( ! defined( 'ABSPATH' ) ) exit;

final class FormulaEngine {

	/** کش سبک برای توکن‌ها و RPN (برای فرمول‌های تکراری) */
	private array $cacheTokens = [];
	private array $cacheRPN    = [];
	private int   $cacheLimit  = 128;

	/**
	 * ارزیابی یک عبارت با مجموعه متغیرها
	 *
	 * @param string $expr
	 * @param array  $vars ['CODE' => number, ...]
	 * @return float
	 */
	public function evaluate( string $expr, array $vars = [] ): float {
		$expr = (string) $expr;

		// 1) سازگاری با قدیمی‌ها: [VAR] → VAR (حذف براکت‌ها)
		$expr = preg_replace('/\[\s*([A-Za-z_][A-Za-z0-9_]*)\s*\]/u', '$1', $expr);

		// 2) shorthand درصد:
		// عددِ چسبیده به % → (عدد/100)
		// نکته: الگو "(\d+(\.\d+)?)%(?!\d)" باعث می‌شود 100%20 (mod) تبدیل نشود.
		$expr = preg_replace('/(\d+(?:\.\d+)?)%(?!\d)/', '($1/100)', $expr);

		// 3) کش توکن‌ها و RPN
		if ( isset($this->cacheRPN[$expr]) ) {
			$rpn = $this->cacheRPN[$expr];
		} else {
			if ( isset($this->cacheTokens[$expr]) ) {
				$tokens = $this->cacheTokens[$expr];
			} else {
				$tokens = $this->tokenize( $expr );
				if ( empty( $tokens ) ) return 0.0;
				$this->remember($this->cacheTokens, $expr, $tokens);
			}
			$rpn = $this->toRPN( $tokens );
			if ( empty( $rpn ) ) return 0.0;
			$this->remember($this->cacheRPN, $expr, $rpn);
		}

		return $this->evalRPN( $rpn, $vars );
	}

	/** مدیریت سادهٔ ظرفیت کش */
	private function remember(array &$cache, string $key, $val): void {
		$cache[$key] = $val;
		if ( count($cache) > $this->cacheLimit ) {
			array_shift($cache);
		}
	}

	// ---------------------------------------------------------------------
	// Tokenize
	// ---------------------------------------------------------------------

	/** تبدیل رشته به توکن‌ها: num, id, op(+ - * / % ^), lparen, rparen, comma */
	private function tokenize( string $s ): array {
		$out = [];
		$len = strlen( $s );
		$i   = 0;

		while ( $i < $len ) {
			$ch = $s[$i];

			// فضاها
			if ( ctype_space( $ch ) ) { $i++; continue; }

			// عدد: 123 | 123.45 | .5
			if ( ctype_digit( $ch ) || ( $ch === '.' && $i+1 < $len && ctype_digit( $s[$i+1] ) ) ) {
				$start = $i++;
				while ( $i < $len && ( ctype_digit( $s[$i] ) || $s[$i] === '.' ) ) $i++;
				$out[] = [ 't' => 'num', 'v' => substr( $s, $start, $i - $start ) ];
				continue;
			}

			// شناسه (متغیر/تابع)
			if ( ctype_alpha( $ch ) || $ch === '_' ) {
				$start = $i++;
				while ( $i < $len && ( ctype_alnum( $s[$i] ) || $s[$i] === '_' ) ) $i++;
				$out[] = [ 't' => 'id', 'v' => substr( $s, $start, $i - $start ) ];
				continue;
			}

			// علائم/پرانتز/کاما
			switch ( $ch ) {
				case '+': case '-': case '*': case '/': case '%': case '^':
					$out[] = [ 't' => 'op', 'v' => $ch ]; $i++; continue 2;
				case '(':
					$out[] = [ 't' => 'lparen' ]; $i++; continue 2;
				case ')':
					$out[] = [ 't' => 'rparen' ]; $i++; continue 2;
				case ',':
					$out[] = [ 't' => 'comma' ];  $i++; continue 2;
			}

			// کاراکتر ناشناخته → نادیده
			$i++;
		}

		return $out;
	}

	// ---------------------------------------------------------------------
	// Shunting-yard → RPN
	// ---------------------------------------------------------------------

	/** تبدیل توکن‌ها به RPN با پشتیبانی از تابع و آرگومان‌ها */
	private function toRPN( array $tokens ): array {
		$out = [];
		$opStack = [];

		// برای توابع: هنگام id و سپس '('، یک توکن func روی استک می‌گذاریم.
		$funcArgCount = []; // تعداد comma ها
		$funcArgSeen  = []; // آیا «توکن آرگومان» دیده شده؟ (برای تشخیص 0 آرگومان)

		$prec       = [ '+' => 2, '-' => 2, '*' => 3, '/' => 3, '%' => 3, '^' => 4, 'u-' => 5 ];
		$rightAssoc = [ '^' => true, 'u-' => true ];

		$prevType = null; // برای تشخیص منفی یک‌جمله‌ای

		for ( $i = 0, $n = count( $tokens ); $i < $n; $i++ ) {
			$t = $tokens[$i];

			switch ( $t['t'] ) {
				case 'num':
					$out[] = $t;
					$prevType = 'num';
					break;

				case 'id':
					// تابع؟ اگر بعدی '(' باشد
					$isFunc = ( $i+1 < $n && $tokens[$i+1]['t'] === 'lparen' );
					if ( $isFunc ) {
						array_push( $opStack, [ 't' => 'func', 'v' => $t['v'] ] );
					} else {
						$out[] = $t; // متغیر معمولی
					}
					$prevType = 'id';
					break;

				case 'lparen':
					array_push( $opStack, [ 't' => 'lparen' ] );
					// اگر قبل از '(' تابع است، یک رکورد شمارش آرگومان باز کن
					if ( count($opStack) >= 2 ) {
						$prev = $opStack[ count($opStack) - 2 ];
						if ( $prev['t'] === 'func' ) {
							$funcArgCount[] = 0;
							$funcArgSeen[]  = false;
						}
					}
					$prevType = 'lparen';
					break;

				case 'rparen':
					// pop until lparen
					while ( !empty( $opStack ) && end( $opStack )['t'] !== 'lparen' ) {
						$out[] = array_pop( $opStack );
					}
					// remove '('
					if ( !empty( $opStack ) && end( $opStack )['t'] === 'lparen' ) {
						array_pop( $opStack );
					}
					// اگر قبل از '(' تابع داریم، آن را به خروجی اضافه کن
					if ( !empty( $opStack ) && end( $opStack )['t'] === 'func' ) {
						$func = array_pop( $opStack );
						// محاسبهٔ تعداد آرگومان‌ها:
						$argc = 0;
						if ( !empty( $funcArgCount ) ) {
							$argc = array_pop( $funcArgCount );
							$seen = array_pop( $funcArgSeen );
							$argc = $seen ? $argc + 1 : 0;
						}
						$out[] = [ 't' => 'func', 'v' => $func['v'], 'argc' => $argc ];
					}
					$prevType = 'rparen';
					break;

				case 'comma':
					// pop تا '('
					while ( !empty( $opStack ) && end( $opStack )['t'] !== 'lparen' ) {
						$out[] = array_pop( $opStack );
					}
					// شمارندهٔ آرگومان را افزایش بده
					if ( !empty( $funcArgCount ) ) {
						$funcArgCount[ count($funcArgCount) - 1 ]++;
					}
					$prevType = 'comma';
					break;

				case 'op':
					$op = $t['v'];
					// منفی یک‌جمله‌ای؟
					if ( $op === '-' && ( $prevType === null || in_array( $prevType, ['op','lparen','comma'], true ) ) ) {
						$op = 'u-';
					}

					$p1 = $prec[ $op ] ?? 0;
					while ( !empty( $opStack ) ) {
						$top = end( $opStack );
						if ( $top['t'] !== 'op' ) break;
						$op2 = $top['v'];
						$p2  = $prec[ $op2 ] ?? 0;

						$assocRight = !empty( $rightAssoc[ $op ] );
						$shouldPop  = $assocRight ? ( $p1 < $p2 ) : ( $p1 <= $p2 );

						if ( $shouldPop ) {
							$out[] = array_pop( $opStack );
						} else {
							break;
						}
					}
					array_push( $opStack, [ 't' => 'op', 'v' => $op ] );

					// در آرگومان تابع، دیدن اپراتور یعنی «توکن آرگومان» دیده شده
					if ( !empty( $funcArgSeen ) ) {
						$funcArgSeen[ count($funcArgSeen) - 1 ] = true;
					}

					$prevType = 'op';
					break;
			}

			// «توکن آرگومان» = عدد یا شناسه (نه پرانتز/کاما) → برای تشخیص foo()
			if ( !empty( $funcArgSeen ) && ($t['t'] === 'num' || $t['t'] === 'id') ) {
				$funcArgSeen[ count($funcArgSeen) - 1 ] = true;
			}
		}

		// خالی‌کردن استک
		while ( !empty( $opStack ) ) {
			$top = array_pop( $opStack );
			if ( $top['t'] === 'lparen' || $top['t'] === 'rparen' ) {
				continue;
			}
			$out[] = $top;
		}

		return $out;
	}

	// ---------------------------------------------------------------------
	// Evaluate RPN
	// ---------------------------------------------------------------------

	/** اجرای RPN با محیط متغیرها (Case-Insensitive) */
	private function evalRPN( array $rpn, array $vars ): float {
		$st = [];

		// lookup متغیرها را Case-Insensitive کن
		$varsL = [];
		foreach ( $vars as $k => $v ) {
			$varsL[ strtolower((string)$k) ] = (float) $v;
		}

		foreach ( $rpn as $t ) {
			switch ( $t['t'] ) {
				case 'num':
					$st[] = (float) $t['v'];
					break;

				case 'id':
					$name = (string) $t['v'];
					$val  = $varsL[ strtolower($name) ] ?? 0.0;
					$st[] = (float) $val;
					break;

				case 'op':
					if ( $t['v'] === 'u-' ) {
						$a = (float) array_pop( $st );
						$st[] = -$a;
						break;
					}
					$b = (float) array_pop( $st );
					$a = (float) array_pop( $st );
					switch ( $t['v'] ) {
						case '+': $st[] = $a + $b;                         break;
						case '-': $st[] = $a - $b;                         break;
						case '*': $st[] = $a * $b;                         break;
						case '/': $st[] = ($b == 0.0) ? 0.0 : ($a / $b);   break;
						case '%': $st[] = ($b == 0.0) ? 0.0 : fmod($a,$b); break;
						case '^': $st[] = pow( $a, $b );                   break;
					}
					break;

				case 'func':
					$fname = strtolower( (string) $t['v'] );
					$argc  = (int) ( $t['argc'] ?? 0 );

					$args = [];
					for ( $k = 0; $k < $argc; $k++ ) {
						array_unshift( $args, (float) array_pop( $st ) ); // وارونه چون LIFO
					}

					$st[] = $this->callFunc( $fname, $args );
					break;
			}
		}

		$res = (float) ( $st[0] ?? 0.0 );
		return $this->isFinite( $res ) ? $res : 0.0;
	}

	/**
	 * نگاشت توابع مجاز
	 * - min/max: آریتی آزاد (>=1). اگر خالی بود 0.
	 * - round(x[, precision])
	 * - ceil/floor/abs: تک‌آرگومانی
	 */
	private function callFunc( string $fname, array $args ): float {
		switch ( $fname ) {
			case 'min':
				return empty( $args ) ? 0.0 : (float) min( $args );
			case 'max':
				return empty( $args ) ? 0.0 : (float) max( $args );
			case 'abs':
				return isset( $args[0] ) ? (float) abs( $args[0] ) : 0.0;
			case 'ceil':
				return isset( $args[0] ) ? (float) ceil( $args[0] ) : 0.0;
			case 'floor':
				return isset( $args[0] ) ? (float) floor( $args[0] ) : 0.0;
			case 'round':
				if ( ! isset( $args[0] ) ) return 0.0;
				$precision = isset( $args[1] ) ? (int) $args[1] : 0;
				return (float) round( $args[0], $precision );
		}
		// تابع ناشناخته → 0
		return 0.0;
	}

	// ---------------------------------------------------------------------
	// Utils
	// ---------------------------------------------------------------------

	private function isFinite( $n ): bool {
		if ( ! is_numeric( $n ) ) return false;
		if ( function_exists( 'is_infinite' ) && is_infinite( $n ) ) return false;
		if ( function_exists( 'is_nan' ) && is_nan( $n ) ) return false;
		return true;
	}
}