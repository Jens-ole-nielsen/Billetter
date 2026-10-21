=== Eventbilletter ===
Contributors: eventbilletter
Tags: events, tickets, stripe, billetter, ticket sales
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sælg billetter til events med Stripe Checkout — uden WooCommerce.

== Description ==

Eventbilletter er et letvægts WordPress-plugin til billetsalg.

* Events som custom post type
* Flere billettyper med pris og kapacitet
* Betaling via Stripe Checkout (ingen WooCommerce)
* Ordrer, billetkoder og bekræftelses-e-mail
* Webhooks til pålidelig betalingsbekræftelse

== Installation ==

1. Upload mappen `eventbilletter` til `/wp-content/plugins/`
2. Aktivér pluginnet under Plugins
3. Gå til Events → Indstillinger og indsæt Stripe-nøgler
4. Opret et webhook i Stripe der peger på REST-URL’en vist under indstillinger
5. Opret et event, tilføj billettyper, og brug shortcoden `[eb_tickets]`

== Shortcodes ==

* `[eb_tickets]` — billetformular (på event-siden)
* `[eb_tickets id="123"]` — billetformular for event 123
* `[eb_checkout_status]` — status efter Stripe-return

== Stripe webhook events ==

* checkout.session.completed
* checkout.session.expired
* charge.refunded

== Changelog ==

= 1.0.0 =
* Første release
