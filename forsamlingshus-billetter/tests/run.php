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
require dirname( __DIR__ ) . '/includes/SalesWindow.php';
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
require dirname( __DIR__ ) . '/includes/OrderExport.php';

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
use Forsamlingshus\Billetter\SalesWindow;
use Forsamlingshus\Billetter\RefundRules;
use Forsamlingshus\Billetter\Pdf\Document;
use Forsamlingshus\Billetter\OrderExport;
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
$covered = TicketFrame::photo_frame( 2000, 1000, 1240, 1754, 1000, 0, 0 );
check( abs( $covered['w'] - 1240 ) < 0.2 && abs( $covered['h'] - 620 ) < 0.2, 'default zoom shows the whole wide photo' );
check( abs( $covered['x'] ) < 0.2 && abs( $covered['y'] ) < 0.2, 'default zoom sits a wide photo at the top' );
$tall_fit = TicketFrame::photo_frame( 1000, 3000, 1240, 1754, 1000, 0, 0 );
check( abs( $tall_fit['h'] - 1754 ) < 0.2 && $tall_fit['w'] < 1240 && abs( ( $tall_fit['x'] + $tall_fit['w'] / 2 ) - 620 ) < 0.3, 'default zoom fits a tall photo and keeps it centered' );
$zoomed = TicketFrame::photo_frame( 2000, 1000, 1240, 1754, 2000, 0, 0 );
check( abs( $zoomed['w'] - ( $covered['w'] * 2 ) ) < 0.2 && abs( $zoomed['h'] - ( $covered['h'] * 2 ) ) < 0.2, '200 percent zoom doubles the photo' );
$moved = TicketFrame::photo_frame( 2000, 1000, 1240, 1754, 1000, 100, -50 );
check( abs( $moved['x'] - $covered['x'] - 124 ) < 0.2 && abs( $moved['y'] - $covered['y'] + 87.7 ) < 0.2, 'dragging moves only the photo by a fraction of the page' );
check( 1000 === TicketFrame::photo_scale( '' ) && 250 === TicketFrame::photo_scale( 10 ) && 4000 === TicketFrame::photo_scale( 9000 ), 'zoom stays between 25 and 400 percent' );
check( 0 === TicketFrame::photo_offset( 'nope' ) && -2000 === TicketFrame::photo_offset( -5000 ) && 2000 === TicketFrame::photo_offset( 9000 ), 'photo drag stays within range' );

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
$cream = array( 255, 251, 245 );
check( array( 28, 28, 28 ) === Color::on_surface( array( 245, 245, 245 ), $cream ), 'light ink on the cream paper becomes dark' );
check( array( 31, 75, 58 ) === Color::on_surface( array( 31, 75, 58 ), $cream ), 'dark green stays readable on the cream paper' );

$layout_page = TicketFrame::size( 'a4' );
$sheet_fields = array( 'heading', 'event', 'date', 'time', 'buyer', 'price' );
$wide_sheet = TicketFrame::sheet( 1600, 757, $layout_page['w'], $layout_page['h'], $sheet_fields, false );
check( abs( ( $wide_sheet['photo']['w'] / $wide_sheet['photo']['h'] ) - ( 1600 / 757 ) ) < 0.01, 'a wide poster keeps its aspect' );
check( abs( $wide_sheet['photo']['w'] - $layout_page['w'] ) < 1 && $wide_sheet['photo']['y'] < 0.1, 'a wide poster fills the top and is not cropped on the sides' );
check( $wide_sheet['photo']['h'] / $layout_page['h'] <= 0.421, 'the poster band stays within the shared cap' );
check( $wide_sheet['blocks']['heading']['y'] > $wide_sheet['photo']['h'] && $wide_sheet['blocks']['price']['y'] > $wide_sheet['photo']['h'], 'the fields start under the poster' );
$field_span = $wide_sheet['blocks']['price']['y'] + $wide_sheet['blocks']['price']['h'] - $wide_sheet['blocks']['heading']['y'];
$field_room = ( $layout_page['h'] - ( $layout_page['h'] * 0.036 ) ) - $wide_sheet['blocks']['heading']['y'];
check( $field_span < ( $field_room * 0.55 ), 'the fields form one block beside the QR card' );
check( abs( ( $wide_sheet['blocks']['date']['y'] + $wide_sheet['blocks']['date']['h'] ) - $wide_sheet['blocks']['time']['y'] ) < 0.5, 'the fields sit against each other' );
check( $wide_sheet['blocks']['qr']['x'] > ( $layout_page['w'] / 2 ) && $wide_sheet['blocks']['qr']['y'] > $wide_sheet['photo']['h'], 'the QR card sits on the right, under the poster' );
$tall_sheet = TicketFrame::sheet( 900, 2200, $layout_page['w'], $layout_page['h'], array( 'heading' ), false );
check( abs( ( $tall_sheet['photo']['h'] / $layout_page['h'] ) - 0.42 ) < 0.002 && $tall_sheet['photo']['x'] > 1 && $tall_sheet['photo']['w'] < $layout_page['w'] - 1, 'a tall poster is letterboxed inside the top band' );
check( abs( ( $tall_sheet['photo']['w'] / $tall_sheet['photo']['h'] ) - ( 900 / 2200 ) ) < 0.01, 'a tall poster is shown whole' );
$logo_sheet = TicketFrame::sheet( 1600, 757, $layout_page['w'], $layout_page['h'], $sheet_fields, true );
check( $logo_sheet['blocks']['logo']['y'] + $logo_sheet['blocks']['logo']['h'] <= $logo_sheet['blocks']['qr']['y'] + 0.2, 'the logo sits above the QR card' );
$dragged_sheet = TicketFrame::sheet( 1600, 757, $layout_page['w'], $layout_page['h'], $sheet_fields, false );
check( $dragged_sheet['photo'] === $wide_sheet['photo'] && $dragged_sheet['blocks']['heading'] === $wide_sheet['blocks']['heading'], 'the sheet does not take a saved drag or zoom' );

$layout_pdf = ( new TicketPdf() )->render_order(
	array(
		array(
			'token' => $tokens[0],
			'sequence' => 1,
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
		'ticket_size' => 'a4',
		'bg_scale' => 2500,
		'bg_x' => 400,
		'bg_y' => 300,
		'blocks' => array(
			'heading' => array(
				'x' => 900,
				'y' => 40,
			),
		),
	),
	null,
	array(
		'data' => $jpeg,
		'w' => 1600,
		'h' => 757,
	)
);
$same_box = static function ( array $got, array $want ): bool {
	foreach ( array( 'x', 'y', 'w', 'h' ) as $key ) {
		if ( abs( $got[ $key ] - $want[ $key ] ) > 0.6 ) {
			return false;
		}
	}

	return true;
};
$pdf_photo = null;
if ( 1 === preg_match( '/re W n ([\d.]+) 0 0 ([\d.]+) ([\d.-]+) ([\d.-]+) cm/', $layout_pdf, $photo_match ) ) {
	$draw_h = (float) $photo_match[2];
	$pdf_photo = array(
		'x' => (float) $photo_match[3],
		'y' => $layout_page['h'] - (float) $photo_match[4] - $draw_h,
		'w' => (float) $photo_match[1],
		'h' => $draw_h,
	);
}
check( is_array( $pdf_photo ) && $same_box( $pdf_photo, $wide_sheet['photo'] ), 'the PDF draws the same poster rectangle as the sheet' );
preg_match_all( '/([\d.-]+) ([\d.-]+) ([\d.-]+) ([\d.-]+) re f/', $layout_pdf, $rect_matches, PREG_SET_ORDER );
$qr_drawn = false;
foreach ( $rect_matches as $rect_match ) {
	$rect_h = (float) $rect_match[4];
	$rect = array(
		'x' => (float) $rect_match[1],
		'y' => $layout_page['h'] - (float) $rect_match[2] - $rect_h,
		'w' => (float) $rect_match[3],
		'h' => $rect_h,
	);
	if ( $same_box( $rect, $wide_sheet['blocks']['qr'] ) ) {
		$qr_drawn = true;
	}
}
check( $qr_drawn, 'the PDF draws the QR card on the sheet rectangle' );
$first_text_x = static function ( string $pdf ): float {
	if ( 1 !== preg_match( '/Tf ([\d.]+) ([\d.-]+) Td/', $pdf, $match ) ) {
		return -1.0;
	}

	return (float) $match[1];
};
check( abs( $first_text_x( $layout_pdf ) - $wide_sheet['blocks']['heading']['x'] ) < 1, 'a saved heading position does not move the text' );
$code_baselines = array();
$manual = Token::grouped( $tokens[0] );
if ( preg_match_all( '/Tf ([\d.-]+) ([\d.-]+) Td \(([^)]*)\) Tj/', $layout_pdf, $text_matches, PREG_SET_ORDER ) ) {
	foreach ( $text_matches as $text_match ) {
		$x = (float) $text_match[1];
		$from_top = $layout_page['h'] - (float) $text_match[2];
		$qr_box = $wide_sheet['blocks']['qr'];
		if ( $x < $qr_box['x'] - 1 || $x > $qr_box['x'] + $qr_box['w'] + 1 ) {
			continue;
		}
		$code_baselines[] = $from_top;
		check( $from_top > $qr_box['y'] && $from_top < ( $qr_box['y'] + $qr_box['h'] - 2 ), 'QR text stays inside the white card' );
	}
}
check( count( $code_baselines ) >= 4, 'the QR card has the ticket line, the code, the order number and the entrance line' );
check( 1 === substr_count( $layout_pdf, $manual ), 'the manual code is printed once, on one line' );
check( ! str_contains( $layout_pdf, '0.961 0.961 0.961 rg' ), 'the sheet does not print light gray text' );

$faint = ( new TicketPdf() )->render_order(
	array(
		array(
			'token' => $tokens[0],
			'sequence' => 1,
		),
	),
	array(
		'heading' => 'Adgangsbillet',
		'buyer' => 'Jens Hansen',
		'primary' => '#F5F5F5',
		'text' => '#F8F8F8',
		'background' => '#FFFBF5',
		'show_event' => false,
		'show_date' => false,
		'show_time' => false,
		'show_buyer' => true,
		'show_price' => false,
		'show_qr' => true,
	)
);
check( str_contains( $faint, '0.110 0.110 0.110 rg' ), 'light text on the cream paper is printed dark' );
check( 2 === substr_count( $ticket_pdf, '/Type /Page ' ), 'one PDF page per ticket' );
check( str_contains( $ticket_pdf, 'Billet 1 af 2' ), 'ticket label stored' );
check( str_contains( $ticket_pdf, Token::grouped( $tokens[0] ) ) && str_contains( $ticket_pdf, Token::grouped( $tokens[1] ) ), 'manual code is on the ticket' );

$code_color = $placed;
$code_color['text'] = '#0000FF';
$code_color['pad_blocks'] = '0';
$code_color['show_event'] = false;
$code_color['show_date'] = false;
$code_color['show_time'] = false;
$code_color['show_buyer'] = false;
$code_color['show_price'] = false;
$code_color['show_qr'] = true;
$code_color['blocks'] = array( 'qr' => array( 'x' => 80, 'y' => 200 ) );
$code_on = ( new TicketPdf() )->render_order(
	array(
		array(
			'token' => $tokens[0],
			'sequence' => 1,
		),
	),
	$code_color,
	null,
	array(
		'data' => $jpeg,
		'w' => 2,
		'h' => 2,
	)
);
check( str_contains( $code_on, '0.000 0.000 0.000 rg' ), 'manual code on the QR is black' );
check( ! str_contains( $code_on, '0.000 0.000 1.000 rg' ), 'manual code on the QR ignores the ticket text color' );
$code_color['show_qr'] = false;
$code_off = ( new TicketPdf() )->render_order(
	array(
		array(
			'token' => $tokens[0],
			'sequence' => 1,
		),
	),
	$code_color,
	null,
	array(
		'data' => $jpeg,
		'w' => 2,
		'h' => 2,
	)
);
check( str_contains( $code_off, Token::grouped( $tokens[0] ) ), 'manual code stays on the ticket when the QR is off' );
check( str_contains( $code_off, '0.000 0.000 1.000 rg' ), 'manual code without the QR uses the ticket text color' );
check( ! str_contains( $code_off, '1.000 1.000 1.000 rg' ), 'manual code without the QR has no white pad' );

$kept = OrderExport::totals( 69800, 0, '', 34900, 2 );
check( 69800 === $kept['amount_ore'] && false === $kept['repaired'], 'a stored order total is left alone' );
$coupon_free = OrderExport::totals( 0, 25000, 'GRATIS', 12500, 2 );
check( 0 === $coupon_free['amount_ore'] && 25000 === $coupon_free['discount_ore'] && false === $coupon_free['repaired'], 'a free coupon stays at zero' );
$code_only = OrderExport::totals( 0, 0, 'SOMMER', 34900, 2 );
check( 0 === $code_only['amount_ore'] && 69800 === $code_only['discount_ore'] && false === $code_only['repaired'], 'a coupon without a stored discount still shows the reduction' );
$missing = OrderExport::totals( 0, 0, '', 34900, 2 );
check( 69800 === $missing['amount_ore'] && true === $missing['repaired'], 'a zero without a coupon is repaired to the event price times quantity' );
$free_event = OrderExport::totals( 0, 0, '', 0, 2 );
check( 0 === $free_event['amount_ore'] && false === $free_event['repaired'], 'a free event stays at zero' );
check( 'Betalingskort' === OrderExport::payment_label( 'stripe' ), 'export calls card payment Betalingskort' );
check( '1.398,00' === OrderExport::amount_csv( 139800 ), 'csv amounts use a Danish decimal without kr' );
$csv = OrderExport::csv(
	array(
		array(
			'number' => 'FHB-00009',
			'event' => 'Lørdagsfest',
			'buyer' => 'Søren',
			'email' => 'soren@example.test',
			'phone' => '12 34 56 78',
			'quantity' => 2,
			'amount_ore' => 0,
			'discount' => 'GRATIS · Rabat 698,00 kr.',
			'payment' => 'Gratis',
			'remark' => 'Vil gerne sidde; ved scenen',
		),
	)
);
check( str_starts_with( $csv, "\xEF\xBB\xBF" ), 'csv starts with a UTF-8 BOM' );
check( str_contains( $csv, ';' ), 'csv uses a semicolon' );
check( str_contains( $csv, 'Lørdagsfest' ) && str_contains( $csv, 'Søren' ), 'csv keeps Danish characters' );
check( str_contains( $csv, '"Vil gerne sidde; ved scenen · GRATIS · Rabat 698,00 kr."' ), 'csv quotes a remark that contains a semicolon and shows the discount' );
check( str_contains( $csv, 'Ordrenummer;Arrangement;Køber;E-mail;Telefon;Antal;Beløb;Betaling;Bemærkning' ), 'csv has the same columns as the order PDF' );

$orders_pdf = ( new OrdersPdf() )->render(
	array(
		array(
			'number' => 'FHB-00012',
			'event' => 'Lørdagsfest',
			'buyer' => 'Søren',
			'email' => 'soren@example.test',
			'phone' => '12 34 56 78',
			'quantity' => 4,
			'amount' => '600,00 kr.',
			'amount_ore' => 60000,
			'discount' => '',
			'payment' => 'Betalingskort',
			'remark' => 'Vil gerne sidde ved scenen',
		),
		array(
			'number' => 'FHB-00013',
			'event' => 'Lørdagsfest',
			'buyer' => 'Anne',
			'email' => 'anne@example.test',
			'phone' => '87 65 43 21',
			'quantity' => 1,
			'amount' => '125,00 kr.',
			'amount_ore' => 12500,
			'discount' => '',
			'payment' => 'Faktura',
			'remark' => '',
		),
		array(
			'number' => 'FHB-00009',
			'event' => 'Lørdagsfest',
			'buyer' => 'Bo',
			'email' => 'bo@example.test',
			'phone' => '11 22 33 44',
			'quantity' => 2,
			'amount' => '0,00 kr.',
			'amount_ore' => 0,
			'discount' => 'GRATIS · Rabat 250,00 kr.',
			'payment' => 'Gratis',
			'remark' => '',
		),
	),
	'Ordrer',
	'Alle arrangementer'
);
check( str_contains( $orders_pdf, 'K\\370ber' ) || str_contains( $orders_pdf, 'Køber' ), 'orders PDF has the buyer column' );
check( str_contains( $orders_pdf, 'E-mail' ) && str_contains( $orders_pdf, 'Telefon' ) && str_contains( $orders_pdf, 'Betaling' ), 'orders PDF has email, phone and payment' );
check( str_contains( $orders_pdf, 'Bem\\346rkning' ) || str_contains( $orders_pdf, 'Bemærkning' ), 'orders PDF has the remark column' );
check( str_contains( $orders_pdf, 'L\\370rdagsfest' ) || str_contains( $orders_pdf, 'Lørdagsfest' ), 'orders PDF keeps Danish characters' );
check( str_contains( $orders_pdf, 'Billetter i alt: 7' ), 'orders PDF totals the tickets' );
check( str_contains( $orders_pdf, 'Bel\\370b i alt: 725,00 kr.' ) || str_contains( $orders_pdf, 'Beløb i alt: 725,00 kr.' ), 'orders PDF sums the payable amounts' );
check( str_contains( $orders_pdf, 'Vil gerne sidde ved scenen' ), 'orders PDF includes the remark' );
check( ! str_contains( $orders_pdf, 'Bemærkning: ' ) && ! str_contains( $orders_pdf, 'Bem\\346rkning: ' ), 'orders PDF does not put the remark on its own line' );
check( str_contains( $orders_pdf, 'GRATIS' ) && ( str_contains( $orders_pdf, 'Rabat 250,00 kr.' ) || str_contains( $orders_pdf, 'Rabat' ) ), 'a free coupon shows the discount in the order PDF' );

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
check( false === Updates::should_refresh( true, time() - 10000, time(), 'admin_init', true ), 'the update click keeps the release already on screen' );
$stored_offer = (object) array(
	'update' => (object) array(
		'version' => '1.6.0',
		'download_url' => 'https://github.com/Jens-ole-nielsen/Billetter/releases/download/v1.6.0/forsamlingshus-billetter.zip',
	),
);
$parsed_offer = Updates::offer_from_state( $stored_offer );
check( is_array( $parsed_offer ) && '1.6.0' === $parsed_offer['version'], 'a cached release keeps its version' );
check( is_array( $parsed_offer ) && str_ends_with( $parsed_offer['package'], 'forsamlingshus-billetter.zip' ), 'a cached release keeps the zip' );
check( null === Updates::offer_from_state( (object) array() ), 'a failed check has no release' );
check( true === Updates::offer_is_newer( '1.6.0', '1.5.0' ), '1.6.0 is newer than the installed plugin' );
check( false === Updates::offer_is_newer( '1.5.0', '1.5.0' ), 'the same version is not offered again' );
check( 1 === Updates::CHECK_PERIOD_HOURS, 'update checks are hourly, not every twelve hours' );
$chosen = Updates::choose_offer(
	array(
		array(
			'version' => '1.6.1',
			'package' => 'https://github.com/Jens-ole-nielsen/Billetter/releases/download/v1.6.1/forsamlingshus-billetter.zip',
		),
		null,
		array(
			'version' => '1.7.0',
			'package' => 'https://github.com/Jens-ole-nielsen/Billetter/releases/download/v1.7.0/forsamlingshus-billetter.zip',
		),
	),
	'1.6.1'
);
check( is_array( $chosen ) && '1.7.0' === $chosen['version'], 'the newest release still ahead of the installed plugin is kept' );
check( null === Updates::choose_offer( array( array( 'version' => '1.6.1', 'package' => 'https://example.test/forsamlingshus-billetter.zip' ) ), '1.6.1' ), 'the installed version is not offered again' );
$stored_transient = (object) array(
	'response' => array(
		'forsamlingshus-billetter/forsamlingshus-billetter.php' => (object) array(
			'new_version' => '1.7.0',
			'package' => 'https://github.com/Jens-ole-nielsen/Billetter/releases/download/v1.7.0/forsamlingshus-billetter.zip',
		),
	),
);
$from_list = Updates::offer_from_transient_value( $stored_transient );
check( is_array( $from_list ) && '1.7.0' === $from_list['version'] && str_ends_with( $from_list['package'], 'forsamlingshus-billetter.zip' ), 'a release WordPress already stored keeps its zip' );
$stripped = clone $stored_transient;
unset( $stripped->response['forsamlingshus-billetter/forsamlingshus-billetter.php'] );
check( null === Updates::offer_from_transient_value( $stripped ), 'a list with the release removed has nothing to install' );
$release_json = json_encode(
	array(
		'tag_name' => 'v1.7.8',
		'draft' => false,
		'prerelease' => false,
		'assets' => array(
			array(
				'name' => 'forsamlingshus-billetter.zip',
				'browser_download_url' => 'https://github.com/Jens-ole-nielsen/Billetter/releases/download/v1.7.8/forsamlingshus-billetter.zip',
			),
		),
	)
);
$from_release = Updates::offer_from_release( $release_json );
check( is_array( $from_release ) && '1.7.8' === $from_release['version'], 'the latest release tag is the offered version' );
check( is_array( $from_release ) && str_ends_with( $from_release['package'], '/forsamlingshus-billetter.zip' ), 'the offered package is the release zip' );
check( true === Updates::offer_is_newer( $from_release['version'], '1.7.7' ), '1.7.8 is newer than an older install' );
check( false === Updates::offer_is_newer( $from_release['version'], '1.7.8' ), 'the installed release is not offered again' );
$without_zip = json_decode( (string) $release_json );
$without_zip->assets[0]->name = 'source.zip';
check( null === Updates::offer_from_release( $without_zip ), 'a release without the plugin zip is not offered' );
$draft = json_decode( (string) $release_json );
$draft->draft = true;
check( null === Updates::offer_from_release( $draft ), 'a draft release is not offered' );
$preview = json_decode( (string) $release_json );
$preview->prerelease = true;
check( null === Updates::offer_from_release( $preview ), 'a pre-release is not offered' );
$options = Updates::request_options( array( 'timeout' => 3 ) );
check( is_array( $options ) && 15 === $options['timeout'], 'GitHub is given fifteen seconds, not three' );
check( is_array( $options ) && 'forsamlingshus-billetter' === ( $options['headers']['User-Agent'] ?? '' ), 'GitHub requests send a user agent' );
$zone = new DateTimeZone( 'Europe/Copenhagen' );
check( '' === SalesWindow::normalize( '  ' ), 'an empty sales start stays empty' );
check( '2026-11-02 19:00' === SalesWindow::normalize( '2026-11-02T19:00' ), 'a sales start keeps the date and time' );
check( null === SalesWindow::normalize( '2026-02-31T19:00' ), 'an impossible sales start is rejected' );
check( '2026-11-02T19:00' === SalesWindow::input_value( '2026-11-02 19:00' ), 'the metabox field uses datetime-local' );
$opens = SalesWindow::start_timestamp( '2026-11-02 19:00', $zone );
check( is_int( $opens ), 'a sales start has a timestamp' );
check( false === SalesWindow::is_open( '2026-11-02 19:00', (int) $opens - 1, $zone ), 'sales stay closed until the start' );
check( true === SalesWindow::is_open( '2026-11-02 19:00', (int) $opens, $zone ), 'sales open at the start' );
check( true === SalesWindow::is_open( '', (int) $opens - 1, $zone ), 'an empty sales start is open immediately' );
check( 'Salget starter om 1 minut og 30 sekunder.' === SalesWindow::countdown_text( (int) $opens - 90, (int) $opens ), 'the countdown names minutes and seconds' );
check( 'Billetsalget er åbent.' === SalesWindow::countdown_text( (int) $opens, (int) $opens ), 'the countdown stops when sales open' );
$event_editor = (string) file_get_contents( dirname( __DIR__ ) . '/includes/EventPostType.php' );
$checkout = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Checkout.php' );
check( str_contains( $event_editor, 'name="fhb_sales_start"' ) && str_contains( $event_editor, '_fhb_sales_start' ), 'the sales start field is saved from the event metabox' );
check( str_contains( $checkout, "'not_started'" ) && str_contains( $checkout, 'SalesWindow::is_open' ), 'checkout refuses an order before sales start' );
check( true === TicketFrame::pads_content( 'auto', true ) && false === TicketFrame::pads_content( 'auto', false ), 'the light pad follows the photo unless chosen' );
check( false === TicketFrame::pads_content( '0', true ) && true === TicketFrame::pads_content( '1', false ), 'the light pad can be forced off or on' );
check( 'auto' === TicketFrame::pad_choice( 'maybe' ), 'an unknown pad choice stays automatic' );
$right_edge = TicketFrame::place( 535.0, 40.0, 280.0, 28.0, 595.28, 841.89 );
check( $right_edge['x'] > 480, 'a block dropped on the right stays on the right' );
check( $right_edge['w'] < 120, 'a block dropped on the right shrinks instead of jumping left' );

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
$front_css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/front.css' );
$sales_page = (string) file_get_contents( dirname( __DIR__ ) . '/includes/SalesPage.php' );
$sales_template = (string) file_get_contents( dirname( __DIR__ ) . '/templates/sales.php' );
check( str_contains( $sales_page, "'fhb-sales-page'" ) && str_contains( $sales_page, 'fhb-hide-page-title' ) && str_contains( $sales_page, 'h1.entry-title' ), 'the sales page hides the theme page title' );
check( str_contains( $sales_page, 'in_the_loop' ) && str_contains( $sales_page, 'is_main_query' ), 'the theme title is cleared only in the sales page loop' );
check( str_contains( $front_css, 'article:has(.fhb-wrap) > h1.entry-title' ) && str_contains( $front_css, '.fhb-title' ), 'the theme heading is hidden only when the sales form is on the page' );
check( ! str_contains( $sales_template, 'fhb-title' ) && str_contains( $sales_template, 'class="fhb-meta"' ) && str_contains( $sales_template, 'name="buyer_name"' ), 'the event name under the poster is gone and the form stays' );
check( str_contains( $sales_template, 'fhb-countdown' ) && str_contains( $sales_template, 'data-fhb-start' ) && str_contains( $sales_template, '$sales_open' ) && str_contains( $front_css, '.fhb-countdown' ), 'the sales page can show a countdown and withholds the form until sales open' );
check( str_contains( $sales_template, 'Tak for din bestilling.' ), 'the receipt line stays' );
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
