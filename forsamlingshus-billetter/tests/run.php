<?php
/**
 * Pure PHP tests. No WordPress install required.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

require dirname( __DIR__ ) . '/vendor/autoload.php';
require dirname( __DIR__ ) . '/includes/Capacity.php';
require dirname( __DIR__ ) . '/includes/Token.php';
require dirname( __DIR__ ) . '/includes/Money.php';
require dirname( __DIR__ ) . '/includes/OrderStatus.php';
require dirname( __DIR__ ) . '/includes/RefundRules.php';
require dirname( __DIR__ ) . '/includes/Payments/PaymentMethod.php';
require dirname( __DIR__ ) . '/includes/Payments/PaymentMethods.php';
require dirname( __DIR__ ) . '/includes/Payments/StripeFailure.php';
require dirname( __DIR__ ) . '/includes/Payments/StripeGateway.php';
require dirname( __DIR__ ) . '/includes/Updates.php';
require dirname( __DIR__ ) . '/includes/Format.php';
require dirname( __DIR__ ) . '/includes/StripeSignature.php';
require dirname( __DIR__ ) . '/includes/Color.php';
require dirname( __DIR__ ) . '/includes/QrCode.php';
require dirname( __DIR__ ) . '/includes/Pdf/Document.php';
require dirname( __DIR__ ) . '/includes/Pdf/TicketPdf.php';
require dirname( __DIR__ ) . '/includes/TicketFrame.php';
require dirname( __DIR__ ) . '/includes/CouponRules.php';
require dirname( __DIR__ ) . '/includes/Events.php';
require dirname( __DIR__ ) . '/includes/Pdf/OrdersPdf.php';

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as QRLibrary;
use chillerlan\QRCode\QROptions;
use Forsamlingshus\Billetter\Capacity;
use Forsamlingshus\Billetter\Format;
use Forsamlingshus\Billetter\Color;
use Forsamlingshus\Billetter\Money;
use Forsamlingshus\Billetter\Payments\PaymentMethods;
use Forsamlingshus\Billetter\Payments\StripeFailure;
use Forsamlingshus\Billetter\Payments\StripeGateway;
use Forsamlingshus\Billetter\Updates;
use Forsamlingshus\Billetter\RefundRules;
use Forsamlingshus\Billetter\Pdf\Document;
use Forsamlingshus\Billetter\Pdf\OrdersPdf;
use Forsamlingshus\Billetter\Pdf\TicketPdf;
use Forsamlingshus\Billetter\QrCode;
use Forsamlingshus\Billetter\StripeSignature;
use Forsamlingshus\Billetter\CouponRules;
use Forsamlingshus\Billetter\Events;
use Forsamlingshus\Billetter\TicketFrame;
use Forsamlingshus\Billetter\Token;

$failed = 0;

function check( bool $condition, string $message ): void {
	global $failed;
	if ( $condition ) {
		echo "ok  {$message}\n";
		return;
	}
	++$failed;
	echo "FAIL {$message}\n";
}

check( 5 === Capacity::remaining( 10, 2, 3 ), 'remaining subtracts reserved and sold' );
check( 0 === Capacity::remaining( 5, 4, 4 ), 'remaining never goes negative' );
check( true === Capacity::can_reserve( 10, 0, 9, 1 ), 'last ticket can be reserved' );
check( false === Capacity::can_reserve( 10, 0, 10, 1 ), 'sold out cannot be reserved' );
check( false === Capacity::can_reserve( 10, 1, 8, 2 ), 'qty must fit exactly' );
check( false === Capacity::can_reserve( 10, 0, 0, 0 ), 'zero qty is rejected' );
check( true === Capacity::can_reserve( 100, 20, 30, 50 ), 'exact remaining is allowed' );

$tokens = array();
$bad_tokens = 0;
for ( $i = 0; $i < 50; $i++ ) {
	$token = Token::generate();
	if ( ! Token::is_valid_format( $token ) || Token::LENGTH !== strlen( $token ) ) {
		++$bad_tokens;
	}
	$tokens[] = $token;
}
check( 0 === $bad_tokens, 'generated tokens have the right format' );
check( 50 === count( array_unique( $tokens ) ), 'generated tokens are unique' );
check( false === Token::is_valid_format( 'abc' ), 'short token is rejected' );
check( false === Token::is_valid_format( strtoupper( $tokens[0] ) ), 'uppercase is not the stored format' );
$grouped = Token::grouped( $tokens[0] );
check( $tokens[0] === Token::normalize( $grouped ), 'grouped token normalizes back' );
check( 8 === count( explode( ' ', $grouped ) ), 'token is shown in eight groups' );

check( 12500 === Money::parse_ore( '125' ), 'whole kroner' );
check( 12550 === Money::parse_ore( '125,50' ), 'Danish comma' );
check( 125050 === Money::parse_ore( '1.250,50' ), 'Danish thousands' );
check( 12550 === Money::parse_ore( '125.50' ), 'dot decimal' );
check( 125000 === Money::parse_ore( '1.250' ), 'thousands without cents' );
check( 50 === Money::parse_ore( '0,50' ), 'cents only' );
check( 0 === Money::parse_ore( '0' ), 'free price' );
check( null === Money::parse_ore( 'abc' ), 'words are rejected' );
check( null === Money::parse_ore( '-5' ), 'negative is rejected' );
check( null === Money::parse_ore( '125,505' ), 'three decimals are rejected' );
check( '125,00 kr.' === Money::format_ore( 12500 ), 'format whole kroner' );
check( '1.250,50 kr.' === Money::format_ore( 125050 ), 'format thousands' );
check( '0,00 kr.' === Money::format_ore( 0 ), 'format zero' );

$full = RefundRules::plan( 'stripe', 'paid', 25000, 0, 2, 'full', null, 0, null );
check( true === $full['ok'] && 25000 === $full['amount_ore'] && true === $full['void_all'] && 'full' === $full['settlement'], 'full refund takes the remaining amount' );
$rest = RefundRules::plan( 'stripe', 'paid', 25000, 10000, 1, 'full', null, 9, null );
check( true === $rest['ok'] && 15000 === $rest['amount_ore'] && 'full' === $rest['settlement'], 'full refund after a partial refunds only the rest' );
$part = RefundRules::plan( 'stripe', 'paid', 25000, 0, 2, 'partial', 5000, 0, null );
check( true === $part['ok'] && 5000 === $part['amount_ore'] && 0 === $part['cancel'] && false === $part['void_all'] && 'partial' === $part['settlement'], 'partial refund can cancel zero tickets' );
$some = RefundRules::plan( 'stripe', 'paid', 25000, 0, 2, 'partial', 12500, 1, 12500 );
check( true === $some['ok'] && 1 === $some['cancel'] && 'partial' === $some['settlement'], 'partial refund can cancel one unused ticket' );
$finish = RefundRules::plan( 'stripe', 'paid', 25000, 20000, 1, 'partial', 5000, 0, 5000 );
check( true === $finish['ok'] && 'full' === $finish['settlement'], 'partial amount that finishes the order is a full settlement' );
check( 'too_much' === RefundRules::plan( 'stripe', 'paid', 25000, 20000, 2, 'partial', 5001, 0, null )['error'], 'refund cannot exceed the remaining amount' );
check( 'bad_amount' === RefundRules::plan( 'stripe', 'paid', 25000, 0, 2, 'partial', 0, 0, null )['error'], 'zero refund amount is rejected' );
check( 'bad_amount' === RefundRules::plan( 'stripe', 'paid', 25000, 0, 2, 'partial', null, 0, null )['error'], 'missing refund amount is rejected' );
check( 'too_many_tickets' === RefundRules::plan( 'stripe', 'paid', 25000, 0, 1, 'partial', 100, 2, null )['error'], 'cannot cancel more tickets than are unused' );
check( 'bad_count' === RefundRules::plan( 'stripe', 'paid', 25000, 0, 2, 'partial', 100, -1, null )['error'], 'negative ticket count is rejected' );
check( 'stripe_short' === RefundRules::plan( 'stripe', 'paid', 25000, 0, 2, 'partial', 5000, 0, 4999 )['error'], 'refund cannot exceed what Stripe still holds' );
check( true === RefundRules::plan( 'stripe', 'paid', 25000, 0, 2, 'full', null, 0, 25000 )['ok'], 'full refund is allowed when Stripe holds the same amount' );
check( 'not_stripe' === RefundRules::plan( 'invoice', 'paid', 25000, 0, 2, 'full', null, 0, null )['error'], 'invoice orders are not Stripe refunds' );
check( 'not_paid' === RefundRules::plan( 'stripe', 'pending', 25000, 0, 2, 'full', null, 0, null )['error'], 'unpaid Stripe orders cannot be refunded' );
check( 'not_paid' === RefundRules::plan( 'stripe', 'awaiting_payment', 25000, 0, 0, 'partial', 100, 0, null )['error'], 'awaiting payment cannot be refunded' );
check( 'nothing_left' === RefundRules::plan( 'stripe', 'paid', 25000, 25000, 0, 'full', null, 0, null )['error'], 'a fully refunded order has nothing left' );
check( 'none' === RefundRules::settlement( 25000, 0 ), 'no refund yet' );
check( 'partial' === RefundRules::settlement( 25000, 1 ), 'any refund below the total is partial' );
check( 'full' === RefundRules::settlement( 25000, 25000 ), 'refunded total is full' );
check( array( 're_abc' ) === RefundRules::ids( ' re_abc , nope ' ), 'only Stripe refund ids are kept' );
check( 're_abc,re_def' === RefundRules::with_id( 're_abc', 're_def' ), 'a new refund id is stored' );
check( 're_abc' === RefundRules::with_id( 're_abc', 're_abc' ), 'the same refund id is not stored twice' );

check(
	array( 'stripe', 'invoice' ) === PaymentMethods::choose_ids( array( 'stripe', 'invoice' ), 12500, true ),
	'stripe stays first when invoice is enabled'
);
check(
	array( 'stripe' ) === PaymentMethods::choose_ids( array( 'stripe', 'invoice' ), 12500, false ),
	'invoice is omitted when the event toggle is off'
);
check(
	array( 'stripe', 'invoice' ) === PaymentMethods::choose_ids( array( 'invoice', 'stripe' ), 12500, true ),
	'stripe is first even if invoice was registered first'
);
check(
	array() === PaymentMethods::choose_ids( array( 'stripe', 'invoice' ), 0, true ),
	'a free event has no payment choice'
);

$payload = '{"id":"evt_test"}';
$secret = 'whsec_test_secret';
$stamp = 1700000000;
$sig = hash_hmac( 'sha256', $stamp . '.' . $payload, $secret );
$header = 't=' . $stamp . ',v1=' . $sig;
check( true === StripeSignature::verify( $payload, $header, $secret, $stamp ), 'valid signature' );
check( true === StripeSignature::verify( $payload, $header, $secret, $stamp + 300 ), 'signature inside tolerance' );
check( false === StripeSignature::verify( $payload, $header, $secret, $stamp + 301 ), 'signature outside tolerance' );
check( false === StripeSignature::verify( $payload, $header, 'whsec_other', $stamp ), 'wrong secret' );
check( false === StripeSignature::verify( $payload . 'x', $header, $secret, $stamp ), 'tampered payload' );
check( false === StripeSignature::verify( $payload, '', $secret, $stamp ), 'missing header' );
check( false === StripeSignature::verify( $payload, $header, '', $stamp ), 'missing secret' );
$other = hash_hmac( 'sha256', $stamp . '.' . $payload, 'nope' );
check( true === StripeSignature::verify( $payload, 't=' . $stamp . ',v1=' . $other . ',v1=' . $sig, $secret, $stamp ), 'accepts one matching v1' );

check( array( 31, 75, 58 ) === Color::rgb( '#1F4B3A' ), 'hex color' );
check( array( 0, 0, 0 ) === Color::rgb( 'nope' ), 'bad color falls back' );

$matrix = QrCode::matrix( $tokens[0] );
$size = count( $matrix );
check( $size >= 21 && $size === count( $matrix[0] ), 'QR matrix is square' );
check( true === $matrix[2][2], 'quiet zone leaves a finder module dark' );
$svg = QrCode::svg_markup( 'FHB-SAMPLE' );
$png = QrCode::png( 'FHB-SAMPLE' );
check( str_starts_with( $png, "\x89PNG" ), 'QR png bytes' );
check( str_starts_with( $svg, '<svg' ), 'preview SVG' );
check( false === str_contains( strtolower( $svg ), '<script' ), 'preview SVG has no script' );

$png_options = new QROptions(
	array(
		'outputType' => QROutputInterface::GDIMAGE_PNG,
		'outputBase64' => false,
		'eccLevel' => EccLevel::M,
		'scale' => 4,
	)
);
$png = ( new QRLibrary( $png_options ) )->render( $tokens[0] );
check( is_string( $png ) && str_starts_with( $png, "\x89PNG" ), 'library can render PNG' );
$decoded = (string) ( new QRLibrary( $png_options ) )->readFromBlob( $png );
check( $decoded === $tokens[0], 'QR payload round-trips the token' );

$doc = new Document();
$doc->add_page();
$doc->fill_rgb( 255, 248, 240 );
$doc->rect( 0, 0, 100, 40, 'F' );
$doc->text( 40, 80, 14, 'Køber (test)', true );
$pdf = $doc->output();
check( str_starts_with( $pdf, '%PDF-1.4' ), 'PDF header' );
check( str_contains( $pdf, '%%EOF' ), 'PDF trailer' );
check( str_contains( $pdf, '\\370' ), 'ø is WinAnsi octal' );
check( 1 === substr_count( $pdf, '/Type /Page ' ), 'one page object' );
$marker = strrpos( $pdf, "startxref\n" );
check( false !== $marker, 'startxref present' );
if ( false !== $marker ) {
	$start = (int) substr( $pdf, $marker + strlen( "startxref\n" ) );
	check( 'xref' === substr( $pdf, $start, 4 ), 'startxref points at xref' );
}

$image = imagecreatetruecolor( 2, 2 );
$red = imagecolorallocate( $image, 200, 20, 20 );
imagefill( $image, 0, 0, $red );
ob_start();
imagejpeg( $image, null, 80 );
$jpeg = (string) ob_get_clean();
imagedestroy( $image );
$with_image = new Document();
$with_image->add_page();
$with_image->jpeg( 20, 20, 30, 30, $jpeg, 2, 2 );
$image_pdf = $with_image->output();
check( str_contains( $image_pdf, '/DCTDecode' ), 'JPEG image is embedded' );
check( str_contains( $image_pdf, $jpeg ), 'JPEG bytes are in the file' );

$ticket_pdf = ( new TicketPdf() )->render_order(
	array(
		array(
			'token' => $tokens[0],
			'sequence' => 1,
		),
		array(
			'token' => $tokens[1],
			'sequence' => 2,
		),
	),
	array(
		'heading' => 'Adgangsbillet',
		'event' => 'Fest i forsamlingshuset',
		'date' => 'lørdag 14. juni 2026',
		'time' => '19:00',
		'buyer' => 'Jens Hansen',
		'price' => '150,00 kr.',
		'order_number' => 'FHB-00012',
		'primary' => '#1F4B3A',
		'text' => '#1C1C1C',
		'background' => '#FFFBF5',
		'show_event' => true,
		'show_date' => true,
		'show_time' => true,
		'show_buyer' => true,
		'show_price' => true,
		'show_qr' => true,
	)
);
check( 1240 === TicketFrame::width_px(), 'ticket image width is 1240 pixels' );
check( 1754 === TicketFrame::height_px(), 'ticket image height is 1754 pixels' );
check( TicketFrame::height_px() > TicketFrame::width_px(), 'ticket image is portrait' );
check( '1240 × 1754 pixels, stående' === TicketFrame::fit_label(), 'design screen states size and orientation' );
check( 8 === TicketFrame::image_id( 8, 3 ), 'a chosen ticket image wins over the poster' );
check( 3 === TicketFrame::image_id( 0, 3 ), 'without a ticket image the poster is used' );
check( 0 === TicketFrame::image_id( 0, 0 ), 'without either image the ticket stays plain' );
check( 8 === TicketFrame::image_id( 8, 3, 5 ), 'a chosen ticket image wins over the poster and the design image' );
check( 3 === TicketFrame::image_id( 0, 3, 5 ), 'an event poster replaces the design image' );
check( 5 === TicketFrame::image_id( 0, 0, 5 ), 'the design image is used when the event has none' );
check( 874 === TicketFrame::pixels( 'a5' )['w'] && 1240 === TicketFrame::pixels( 'a5' )['h'], 'A5 portrait pixel size' );
check( '874 × 1240 pixels, stående' === TicketFrame::fit_label( 'a5' ), 'A5 label states pixels and orientation' );
check( 620 === TicketFrame::pixels( 'a6' )['w'] && 874 === TicketFrame::pixels( 'a6' )['h'], 'A6 portrait pixel size' );
check( 1240 === TicketFrame::pixels( 'a5l' )['w'] && 874 === TicketFrame::pixels( 'a5l' )['h'], 'A5 landscape pixel size' );
check( '1240 × 874 pixels, liggende' === TicketFrame::fit_label( 'a5l' ), 'landscape label' );
check( 'a4' === TicketFrame::size( 'nope' )['key'], 'unknown size falls back to A4' );
check( 0 === TicketFrame::mille( -20 ) && 1000 === TicketFrame::mille( 4000 ), 'positions stay inside the page' );
check( abs( TicketFrame::origin( 595.28, 500 ) - 297.64 ) < 0.02, 'a saved position is a fraction of the page' );
$wide = TicketFrame::cover_crop( 2000, 1000, 1240, 1754 );
check( 1000 === $wide['h'] && $wide['w'] < 2000 && $wide['x'] > 0, 'a wide photo is cropped on the sides to fill the ticket' );
$tall = TicketFrame::cover_crop( 1000, 3000, 1240, 1754 );
check( 1000 === $tall['w'] && $tall['h'] < 3000 && $tall['y'] > 0, 'a tall photo is cropped top and bottom' );

$photo_pdf = ( new TicketPdf() )->render_order(
	array(
		array(
			'token' => $tokens[0],
			'sequence' => 1,
		),
	),
	array(
		'heading' => 'Adgangsbillet',
		'event' => 'Fest',
		'date' => 'lørdag 14. juni 2026',
		'time' => '19:00',
		'buyer' => 'Jens Hansen',
		'price' => '150,00 kr.',
		'order_number' => 'FHB-00012',
		'primary' => '#1F4B3A',
		'text' => '#1C1C1C',
		'background' => '#FFFBF5',
		'show_event' => true,
		'show_qr' => true,
	),
	null,
	array(
		'data' => $jpeg,
		'w' => 2,
		'h' => 2,
	)
);
check( str_contains( $photo_pdf, '/DCTDecode' ), 'event image is embedded on the ticket' );
check( str_contains( $photo_pdf, $jpeg ), 'ticket stores the cropped jpeg' );

check( str_contains( $ticket_pdf, '/MediaBox [0 0 595.28 841.89]' ), 'default ticket is still A4' );
check( str_starts_with( $ticket_pdf, '%PDF-1.4' ), 'ticket PDF header' );

$placed = array(
	'heading' => 'Adgangsbillet',
	'event' => 'Fest',
	'date' => 'lørdag 14. juni 2026',
	'time' => '19:00',
	'buyer' => 'Jens Hansen',
	'price' => '150,00 kr.',
	'order_number' => 'FHB-00012',
	'primary' => '#1F4B3A',
	'text' => '#1C1C1C',
	'background' => '#FFFBF5',
	'show_event' => true,
	'show_qr' => true,
	'ticket_size' => 'a6',
);
$small_pdf = ( new TicketPdf() )->render_order(
	array(
		array(
			'token' => $tokens[0],
			'sequence' => 1,
		),
	),
	$placed
);
check( str_contains( $small_pdf, '/MediaBox [0 0 297.64 419.53]' ), 'chosen ticket size changes the PDF page' );
$placed['ticket_size'] = 'a4';
$placed['blocks'] = array( 'heading' => array( 'x' => 40, 'y' => 40 ) );
$left_pdf = ( new TicketPdf() )->render_order(
	array(
		array(
			'token' => $tokens[0],
			'sequence' => 1,
		),
	),
	$placed
);
$placed['blocks'] = array( 'heading' => array( 'x' => 620, 'y' => 40 ) );
$right_pdf = ( new TicketPdf() )->render_order(
	array(
		array(
			'token' => $tokens[0],
			'sequence' => 1,
		),
	),
	$placed
);
$first_text_x = static function ( string $pdf ): float {
	if ( 1 !== preg_match( '/Tf ([\d.]+) ([\d.]+) Td/', $pdf, $match ) ) {
		return -1;
	}

	return (float) $match[1];
};
check( $first_text_x( $right_pdf ) > $first_text_x( $left_pdf ) + 40, 'a saved heading position moves the text on the PDF' );
check( 2 === substr_count( $ticket_pdf, '/Type /Page ' ), 'one PDF page per ticket' );
check( str_contains( $ticket_pdf, 'Billet 1 af 2' ) || str_contains( $ticket_pdf, 'Billet 1 af 2' ), 'ticket label stored' );
$grouped_parts = explode( ' ', Token::grouped( $tokens[0] ) );
$grouped_first = implode( ' ', array_slice( $grouped_parts, 0, 4 ) );
$grouped_second = implode( ' ', array_slice( $grouped_parts, 4 ) );
check( str_contains( $ticket_pdf, $grouped_first ) && str_contains( $ticket_pdf, $grouped_second ), 'manual code is on the ticket' );

$orders_pdf = ( new OrdersPdf() )->render(
	array(
		array(
			'number' => 'FHB-00012',
			'buyer' => 'Jens Hansen',
			'quantity' => 4,
			'remark' => 'Vil gerne sidde ved scenen',
			'amount' => '600,00 kr.',
		),
	),
	'Ordrer',
	'Fest'
);
check( str_contains( $orders_pdf, 'K\\370ber' ) || str_contains( $orders_pdf, 'Køber' ), 'orders PDF has the buyer column' );
check( str_contains( $orders_pdf, 'Billetter i alt: 4' ), 'orders PDF totals the tickets' );
check( str_contains( $orders_pdf, 'Vil gerne sidde ved scenen' ), 'orders PDF includes the remark' );

$fields = StripeGateway::session_fields(
	12,
	'kober@example.test',
	2,
	12500,
	'Lørdagsfest',
	13,
	'https://example.test/billetter/?fhb_result=success&order=abc',
	'https://example.test/billetter/?fhb_result=cancel&order=abc',
	1_700_000_000
);
check( ! array_key_exists( 'automatic_payment_methods[enabled]', $fields ), 'checkout session does not send automatic_payment_methods' );
check( 'dkk' === $fields['line_items[0][price_data][currency]'], 'checkout currency is dkk' );
check( '12500' === $fields['line_items[0][price_data][unit_amount]'], 'unit amount is øre' );
check( '2' === $fields['line_items[0][quantity]'], 'stripe quantity is the ticket count' );
check( 'payment' === $fields['mode'], 'checkout mode is payment' );
check( str_starts_with( $fields['success_url'], 'https://' ), 'success url is absolute' );
check( str_starts_with( $fields['cancel_url'], 'https://' ), 'cancel url is absolute' );
check( StripeGateway::SESSION_SECONDS >= 1860, 'session lifetime is more than 30 minutes' );

check( 'payment_key_missing' === StripeFailure::buyer_code( 'Stripe-nøglen mangler.', '', 0 ), 'missing key is named' );
check( 'payment_key' === StripeFailure::buyer_code( 'Invalid API Key provided: sk_test_secret', 'invalid_api_key', 401 ), 'rejected key is named' );
check( 'payment_min' === StripeFailure::buyer_code( 'Amount must be at least 250 øre', 'amount_too_small', 400 ), 'amount under 2.50 kr is named' );
check( 'payment' === StripeFailure::buyer_code( 'Received unknown parameter', 'parameter_unknown', 400 ), 'other stripe errors stay generic' );
check( 'fhb stripe checkout code=amount_too_small http=400' === StripeFailure::log_line( 'amount_too_small', 400 ), 'log line is the code and status' );
check( ! str_contains( StripeFailure::log_line( 'invalid_api_key', 401 ), 'sk_' ), 'log line has no secret' );
check( str_starts_with( StripeFailure::admin_note( 'amount_too_small', 'Amount must be at least 250' ), 'amount_too_small:' ), 'admin note keeps the stripe code' );

check( '' === Updates::token_for_auth( '   ' ), 'blank github token is not sent' );
check( 'ghp_example' === Updates::token_for_auth( ' ghp_example ' ), 'a real token is trimmed' );
check( true === Updates::should_refresh( false, time() - 121, time(), 'load-plugins.php' ), 'plugins screen refreshes after two minutes' );
check( false === Updates::should_refresh( false, time() - 30, time(), 'load-plugins.php' ), 'plugins screen does not refresh immediately' );
check( false === Updates::should_refresh( false, time() - 10000, time(), 'admin_init' ), 'ordinary admin pages keep the hourly cache' );
check( true === Updates::should_refresh( true, time(), time(), 'admin_init' ), 'a due check still runs' );
check( 1 === Updates::CHECK_PERIOD_HOURS, 'update checks are hourly, not every twelve hours' );

check( 'SOMMER20' === CouponRules::normalize( ' sommer20 ' ), 'coupon codes are trimmed and uppercased' );
check( true === CouponRules::valid_code( 'SOMMER20' ), 'a normal coupon code is accepted' );
check( false === CouponRules::valid_code( 'A' ), 'a one-character code is rejected' );
$percent = CouponRules::price( 12500, 2, CouponRules::PERCENT, 10, 0 );
check( 2500 === $percent['discount_ore'] && 22500 === $percent['payable_ore'], 'ten percent comes off the order' );
$one_free = CouponRules::price( 12500, 3, CouponRules::TICKETS, 0, 1 );
check( 12500 === $one_free['discount_ore'] && 25000 === $one_free['payable_ore'], 'the first ticket is free and the rest stay full price' );
$all_covered = CouponRules::price( 12500, 2, CouponRules::TICKETS, 0, 5 );
check( 0 === $all_covered['payable_ore'], 'buying fewer tickets than the free count makes the order free' );
$all_free = CouponRules::price( 12500, 4, CouponRules::FREE, 0, 0 );
check( 0 === $all_free['payable_ore'] && 50000 === $all_free['discount_ore'], 'all tickets free zeros the order' );
check( false === CouponRules::accept( false, true, '', '2026-10-25', 0, 4, 0, 0 )['ok'], 'an unknown code is rejected' );
check( 'expired' === CouponRules::accept( true, true, '2026-10-01', '2026-10-25', 0, 4, 0, 0 )['error'], 'an expired code is rejected' );
check( 'used_up' === CouponRules::accept( true, true, '', '2026-10-25', 0, 4, 1, 1 )['error'], 'a used-up code is rejected' );
check( 'wrong_event' === CouponRules::accept( true, true, '', '2026-10-25', 9, 4, 0, 0 )['error'], 'a code for another event is rejected' );
check( true === CouponRules::accept( true, true, '', '2026-10-25', 0, 4, 0, 3 )['ok'], 'an open code is accepted for every event' );
$rejected = CouponRules::price( 12500, 2, CouponRules::PERCENT, 10, 0 );
check( 25000 === $rejected['gross_ore'], 'a rejected code does not change the gross price the buyer started from' );
check( true === Events::shows_remaining_count( '' ), 'existing events keep the public ticket count' );
check( true === Events::shows_remaining_count( '1' ), 'an enabled count stays visible' );
check( false === Events::shows_remaining_count( '0' ), 'the public count can be hidden' );

$saturday = new DateTimeImmutable( '2026-11-14 12:00:00' );
check( 'lørdag 14. november 2026' === Format::long_date( $saturday ), 'event date is Danish' );

$html_options = new \Dompdf\Options();
$html_options->set( 'defaultFont', 'DejaVu Sans' );
$html_pdf = new \Dompdf\Dompdf( $html_options );
$html_pdf->loadHtml( '<html><body><p>Billet æøå</p></body></html>', 'UTF-8' );
$html_pdf->render();
$html_binary = $html_pdf->output();
check( is_string( $html_binary ) && str_starts_with( $html_binary, '%PDF' ), 'dompdf produces a PDF' );

if ( $failed > 0 ) {
	echo "\n{$failed} failed\n";
	exit( 1 );
}

echo "\nAll tests passed\n";
exit( 0 );
