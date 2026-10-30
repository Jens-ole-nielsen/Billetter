<?php
/**
 * Human order numbers.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter;

defined( 'ABSPATH' ) || exit;

/**
 * Stable order number shown to buyers and staff.
 */
final class OrderNumber {

	public static function format( int $id ): string {
		return 'FHB-' . str_pad( (string) $id, 5, '0', STR_PAD_LEFT );
	}
}
