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
require dirname( __DIR__ ) . '/includes/Format.php';
require dirname( __DIR__ ) . '/includes/StripeSignature.php';
require dirname( __DIR__ ) . '/includes/Color.php';
require dirname( __DIR__ ) . '/includes/QrCode.php';
require dirname( __DIR__ ) . '/includes/Pdf/Document.php';
require dirname( __DIR__ ) . '/includes/Pdf/TicketPdf.php';
require dirname( __DIR__ ) . '/includes/Pdf/OrdersPdf.php';

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as QRLibrary;
use chillerlan\QRCode\QROptions;
use Forsamlingshus\Billetter\Capacity;
use Forsamlingshus\Billetter\Format;
use Forsamlingshus\Billetter\Color;
use Forsamlingshus\Billetter\Money;
use Forsamlingshus\Billetter\Pdf\Document;
use Forsamlingshus\Billetter\Pdf\OrdersPdf;
use Forsamlingshus\Billetter\Pdf\TicketPdf;
use Forsamlingshus\Billetter\QrCode;
use Forsamlingshus\Billetter\StripeSignature;
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
check( str_starts_with( $ticket_pdf, '%PDF-1.4' ), 'ticket PDF header' );
check( 2 === substr_count( $ticket_pdf, '/Type /Page ' ), 'one PDF page per ticket' );
check( str_contains( $ticket_pdf, 'Billet 1 af 2' ) || str_contains( $ticket_pdf, 'Billet 1 af 2' ), 'ticket label stored' );
check( str_contains( $ticket_pdf, Token::grouped( $tokens[0] ) ) || str_contains( $ticket_pdf, str_replace( ' ', '', Token::grouped( $tokens[0] ) ) ), 'manual code is on the ticket' );

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

$saturday = new DateTimeImmutable( '2026-11-14 12:00:00' );
check( 'lørdag 14. november 2026' === Format::long_date( $saturday ), 'event date is Danish' );

if ( $failed > 0 ) {
	echo "\n{$failed} failed\n";
	exit( 1 );
}

echo "\nAll tests passed\n";
exit( 0 );
