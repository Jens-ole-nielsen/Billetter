=== Forsamlingshus Billetter ===
Contributors: jens-ole-nielsen
Tags: tickets, events, stripe, mobilepay
Requires at least: 6.4
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sælg billetter til arrangementer i forsamlingshuset uden WooCommerce.

== Description ==

Pluginnet laver en salgsside, når I opretter et arrangement. Køberen betaler med Stripe Checkout (kort, MobilePay og de metoder, I har slået til) eller med faktura efter aftale. Billetterne sendes som én PDF med en QR-kode pr. billet. Dørvagten scanner koden og markerer den ene billet som fremmødt.

== Installation ==

1. Kopiér mappen `forsamlingshus-billetter` til `/wp-content/plugins/`.
2. Aktivér pluginnet.
3. Gå til Billetter → Indstillinger, og gem Stripe-nøglerne.

== Stripe ==

I skal bruge tre nøgler fra Stripe:

* Publishable key, som starter med `pk_test_` eller `pk_live_`.
* Secret key, som starter med `sk_test_` eller `sk_live_`.
* Webhook secret, som starter med `whsec_`.

Webhook-URL'en vises på indstillingssiden. Tilføj et endpoint i Stripe, og vælg `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed` og `checkout.session.expired`.

Billetterne sendes først, når webhooken har tjekket signaturen og markeret ordren betalt.

== Ofte stillede spørgsmål ==

= Hvordan opretter jeg et arrangement? =

Gå til Billetter → Arrangementer. Udfyld navn, beskrivelse, plakat, antal billetter, dato, tid og pris. Udgiv arrangementet. Salgssiden oprettes automatisk, og linket står i boksen Billetsalg.

= Hvordan virker indtjekning? =

En bruger med rollen Dørvagt åbner Billetter → Indtjekning, scanner QR-koden eller taster koden. Den ene billet bliver fremmødt. Scannes den igen, vises det, og I kan se, hvilke af køberens øvrige billetter der stadig er ubrugte. Kameraet kræver HTTPS.

= Slettes ordrerne, hvis pluginnet deaktiveres? =

Nej. Deaktivering og almindelig sletning beholder ordrer og billetter. Sæt `define( 'FHB_REMOVE_DATA', true );` i wp-config.php, før pluginnet slettes, hvis dataene også skal væk.

= Kan jeg bruge Quickpay? =

Nej. Stripe dækker kort og MobilePay, når det er slået til i Stripe. Faktura efter aftale er den anden mulighed.

== Changelog ==

= 1.3.0 =
* Stripe er valgt på forhånd. Faktura vises kun, når den er slået til på arrangementet, og køberen skal bekræfte, at den er aftalt.
* På arrangementet kan salget nulstilles. Ordrer og billetter til det arrangement slettes. Penge i Stripe refunderes ikke.

= 1.2.0 =
* En betalt Stripe-ordre kan refunderes helt eller delvist. Beløbet sendes til Stripe.
* Fuld refundering annullerer billetterne og lægger pladserne tilbage. Delvis refundering kan annullere et valgt antal ubrugte billetter, eller ingen.
* Ordren viser betalingsmåde, refunderet beløb, om refunderingen er fuld eller delvis, og Stripe-refunderingens id.

= 1.1.0 =
* Oversigten viser, hvor mange billetter der er solgt. Tallet er betalte billetter.
* En Elementor-skabelon kan bruges som billet, når Elementor er aktivt. Ellers bruges det indbyggede design.
* En MailPoet-mail kan bruges som tekst i billetmailen. PDF-filen vedhæftes stadig. Ellers bruges tekstmailen.
* Opdateringer kan hentes fra GitHub-udgivelser på Jens-ole-nielsen/Billetter. Et offentligt arkiv læses uden token. Et privat arkiv kræver et token under Indstillinger.
* På en betalt ordre kan billet-PDF'en åbnes, og billetmailen kan sendes igen.

= 1.0.0 =
* Første version med arrangementer, Stripe Checkout, faktura, PDF-billetter, indtjekning og mail til købere.
