<?php
/**
 * Renders a MailPoet newsletter as the ticket email body.
 *
 * @package ForsamlingshusBilletter
 */

declare(strict_types=1);

namespace Forsamlingshus\Billetter\Mail;

defined( 'ABSPATH' ) || exit;

/**
 * MailPoet supplies the HTML. Sending stays on wp_mail so the PDF can be attached.
 */
final class MailPoetBody {

	public static function available(): bool {
		return class_exists( '\MailPoet\DI\ContainerWrapper' )
			&& class_exists( '\MailPoet\Newsletter\NewslettersRepository' )
			&& class_exists( '\MailPoet\Newsletter\Renderer\Renderer' );
	}

	/**
	 * @return array<int, string>
	 */
	public static function choices(): array {
		if ( ! self::available() ) {
			return array();
		}

		try {
			$repository = \MailPoet\DI\ContainerWrapper::getInstance()->get( \MailPoet\Newsletter\NewslettersRepository::class );
			$rows = $repository->findAll();
		} catch ( \Throwable $error ) {
			unset( $error );
			return array();
		}

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$choices = array();
		foreach ( $rows as $newsletter ) {
			if ( ! is_object( $newsletter ) || ! method_exists( $newsletter, 'getId' ) ) {
				continue;
			}
			if ( method_exists( $newsletter, 'getDeletedAt' ) && null !== $newsletter->getDeletedAt() ) {
				continue;
			}
			$id = (int) $newsletter->getId();
			if ( $id < 1 ) {
				continue;
			}
			$subject = method_exists( $newsletter, 'getSubject' ) ? trim( (string) $newsletter->getSubject() ) : '';
			$choices[ $id ] = '' !== $subject ? $subject : sprintf(
				/* translators: %d: newsletter id */
				__( 'Mail #%d', 'forsamlingshus-billetter' ),
				$id
			);
		}
		asort( $choices, SORT_NATURAL | SORT_FLAG_CASE );

		return $choices;
	}

	/**
	 * HTML with ticket shortcodes already replaced. Null means the plain email should be used.
	 */
	public function html( int $newsletter_id ): ?string {
		if ( $newsletter_id < 1 || ! self::available() ) {
			return null;
		}

		try {
			$container = \MailPoet\DI\ContainerWrapper::getInstance();
			$repository = $container->get( \MailPoet\Newsletter\NewslettersRepository::class );
			$newsletter = $repository->findOneById( $newsletter_id );
			if ( ! is_object( $newsletter ) ) {
				return null;
			}
			$renderer = $container->get( \MailPoet\Newsletter\Renderer\Renderer::class );
			$rendered = $renderer->render( $newsletter );
		} catch ( \Throwable $error ) {
			unset( $error );
			return null;
		}

		$html = '';
		if ( is_array( $rendered ) ) {
			$html = isset( $rendered['html'] ) ? (string) $rendered['html'] : '';
		} elseif ( is_string( $rendered ) ) {
			$html = $rendered;
		}
		if ( '' === trim( $html ) ) {
			return null;
		}

		$html = str_replace(
			array( '&#91;', '&#93;', '&lsqb;', '&rsqb;' ),
			array( '[', ']', '[', ']' ),
			$html
		);
		$html = do_shortcode( $html );
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return null;
		}

		return $html;
	}
}
