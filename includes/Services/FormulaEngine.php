<?php
/**
 * Services\FormulaEngine
 *
 * Lightweight and secure engine for evaluating mathematical expressions with variables.
 * Support for:
 *  - Operators: + - * / % ^  (right-associative power), unary negative (u-)
 *  - Parentheses and argument separator (,)
 *  - Variables: [A-Za-z_][A-Za-z0-9_]*  (also compatible with [VAR])
 *  - Functions: min(...), max(...), abs(x), ceil(x), floor(x), round(x[, precision])
 *  - Shorthand percent: 5% ⇒ (5/100)
 *
 * Without eval; with Shunting-yard and RPN, compatible with old Navasan.
 *
 * File: includes/Services/FormulaEngine.php
 */

namespace MNS\NavasanPlus\Services;

if ( ! defined( 'ABSPATH' ) ) exit;

final class FormulaEngine {

	/** Lightweight cache for tokens and RPN (for repetitive formulas) */
	private array $cacheTokens = [];
	private array $cacheRPN    = [];
	private int   $cacheLimit  = 128;

	/**
	 * Evaluate an expression with a set of variables
	 *
	 * @param string $expr
	 * @param array  $vars ['CODE' => number, ...]
	 * @return float
	 */
	public function evaluate( string $expr, array $vars = [] ): float {
		$expr = (string) $expr;

		// 1) Compatibility with old: [VAR] → VAR (remove brackets)
		$expr = preg_replace('/\[\s*([A-Za-z_][A-Za-z0-9_]*)\s*\]/u', '$1', $expr);

		// 2) shorthand percent:
		// Number stuck to % → (number/100)
		// Note: Pattern "(\d+(\.\d+)?)%(?!\d)" prevents 100%20 (mod) from converting.
		$expr = preg_replace('/(\d+(?:\.\d+)?)%(?!\d)/', '($1/100)', $expr);

		// 3) Cache tokens and RPN
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

	/** Simple cache capacity management */
	private function remember(array &$cache, string $key, $val): void {
		$cache[$key] = $val;
		if ( count($cache) > $this->cacheLimit ) {
			array_shift($cache);
		}
	}

	// ---------------------------------------------------------------------
	// Tokenize
	// ---------------------------------------------------------------------

	/** Convert string to tokens: num, id, op(+ - * / % ^), lparen, rparen, comma */
	private function tokenize( string $s ): array {
		$out = [];
		$len = strlen( $s );
		$i   = 0;

		while ( $i < $len ) {
			$ch = $s[$i];

			// Spaces
			if ( ctype_space( $ch ) ) { $i++; continue; }

			// Number: 123 | 123.45 | .5
			if ( ctype_digit( $ch ) || ( $ch === '.' && $i+1 < $len && ctype_digit( $s[$i+1] ) ) ) {
				$start = $i++;
				while ( $i < $len && ( ctype_digit( $s[$i] ) || $s[$i] === '.' ) ) $i++;
				$out[] = [ 't' => 'num', 'v' => substr( $s, $start, $i - $start ) ];
				continue;
			}

			// Identifier (variable/function)
			if ( ctype_alpha( $ch ) || $ch === '_' ) {
				$start = $i++;
				while ( $i < $len && ( ctype_alnum( $s[$i] ) || $s[$i] === '_' ) ) $i++;
				$out[] = [ 't' => 'id', 'v' => substr( $s, $start, $i - $start ) ];
				continue;
			}

			// Symbols/parentheses/comma
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

			// Unknown character → ignore
			$i++;
		}

		return $out;
	}

	// ---------------------------------------------------------------------
	// Shunting-yard → RPN
	// ---------------------------------------------------------------------

	/** Convert tokens to RPN with function and argument support */
	private function toRPN( array $tokens ): array {
		$out = [];
		$opStack = [];

		// For functions: when id and then '(', we put a func token on stack.
		$funcArgCount = []; // Number of commas
		$funcArgSeen  = []; // Was "argument token" seen? (to detect 0 arguments)

		$prec       = [ '+' => 2, '-' => 2, '*' => 3, '/' => 3, '%' => 3, '^' => 4, 'u-' => 5 ];
		$rightAssoc = [ '^' => true, 'u-' => true ];

		$prevType = null; // For detecting unary negative

		for ( $i = 0, $n = count( $tokens ); $i < $n; $i++ ) {
			$t = $tokens[$i];

			switch ( $t['t'] ) {
				case 'num':
					$out[] = $t;
					$prevType = 'num';
					break;

				case 'id':
					// Function? If next is '('
					$isFunc = ( $i+1 < $n && $tokens[$i+1]['t'] === 'lparen' );
					if ( $isFunc ) {
						array_push( $opStack, [ 't' => 'func', 'v' => $t['v'] ] );
					} else {
						$out[] = $t; // Regular variable
					}
					$prevType = 'id';
					break;

				case 'lparen':
					array_push( $opStack, [ 't' => 'lparen' ] );
					// If there's a function before '(', open an argument count record
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
					// If we have a function before '(', add it to output
					if ( !empty( $opStack ) && end( $opStack )['t'] === 'func' ) {
						$func = array_pop( $opStack );
						// Calculationٔ تعداد آرگومان‌ها:
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

					// در آرگومان Function، دیدن اپراتور یعنی «Token آرگومان» دیده شده
					if ( !empty( $funcArgSeen ) ) {
						$funcArgSeen[ count($funcArgSeen) - 1 ] = true;
					}

					$prevType = 'op';
					break;
			}

			// «Token آرگومان» = عدد یا ID (نه پرانتز/کاما) → برای تشخیص foo()
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

	/** اجرای RPN با محیط Variableها (Case-Insensitive) */
	private function evalRPN( array $rpn, array $vars ): float {
		$st = [];

		// lookup Variableها را Case-Insensitive کن
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
		// Function ناشناخته → 0
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