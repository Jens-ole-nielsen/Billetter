# Forsamlingshus Billetter

Et enkelt billetsystem til arrangementer i forsamlingshuset. Pluginnet kan køre uden WooCommerce.

Når du opretter et arrangement, laver pluginnet en offentlig salgsside med plakat, beskrivelse, dato, tid, pris, antal ledige billetter og en bestillingsformular.

## Installation

1. Kopiér mappen `forsamlingshus-billetter` til `wp-content/plugins/`.
2. Aktivér **Forsamlingshus Billetter** i WordPress.
3. Gå til **Billetter → Indstillinger**, og udfyld Stripe-nøglerne.

Pluginnet kræver WordPress 6.4 eller nyere og PHP 8.1 eller nyere. Ved aktivering oprettes databasetabellerne og rollen **Dørvagt**.

## Stripe

Betalingen sker i Stripe Checkout. Kortoplysninger rører aldrig forsamlingshusets server. Pluginnet beder Stripe om automatiske betalingsmetoder, så køberen får det, I har slået til i Stripe — typisk kort og MobilePay i Danmark, plus andre metoder fra dashboardet.

I **Billetter → Indstillinger** skal I gemme:

| Felt | Starter med | Bruges til |
| --- | --- | --- |
| Publishable key | `pk_test_` eller `pk_live_` | Gemmes sammen med de andre nøgler. Den vises ikke på salgssiden, fordi betalingen er hosted hos Stripe. |
| Secret key | `sk_test_` eller `sk_live_` | Opretter Checkout-sessionen. |
| Webhook secret | `whsec_` | Bekræfter, at beskeden virkelig kommer fra Stripe. |

Webhook-URL'en står på indstillingssiden. I Stripe: **Developers → Webhooks → Add endpoint**, og vælg disse hændelser:

- `checkout.session.completed`
- `checkout.session.async_payment_succeeded`
- `checkout.session.async_payment_failed`
- `checkout.session.expired`

Først når webhooken har godkendt signaturen og beløbet, markeres ordren som betalt, og billetterne sendes. Startsiden efter betaling er kun en kvittering.

Til test bruges `sk_test_` / `pk_test_` og en webhook-hemmelighed fra en test-endpoint. Stripe CLI kan videresende webhooks lokalt.

## Faktura efter aftale

På hvert arrangement er der en afkrydsning: **Betaling via faktura (efter aftale)**.

- Slået til: køberen kan vælge faktura. Ordren står som **Afventer betaling**, og billetterne sendes først, når en administrator markerer den som betalt.
- Slået fra: der er kun Stripe.

Faktura her er en aftale med huset. Pluginnet laver ikke en EAN- eller momsfaktura.

## Opret et arrangement

1. Gå til **Billetter → Arrangementer → Tilføj nyt**.
2. Skriv navn og beskrivelse.
3. Vælg plakat fra mediebiblioteket (det samme billede som på Facebook).
4. Udfyld antal billetter, dato, tidspunkt og pris i kroner. `0` er et gratis arrangement, og så sendes billetterne med det samme.
5. Udgiv arrangementet.

Salgssiden er en almindelig WordPress-side med kortkoden `[fhb_event id="…"]`. Linket står i boksen **Billetsalg** og i oversigten.

Køberen oplyser navn, e-mail, telefon, antal og en valgfri bemærkning (for eksempel at de vil sidde sammen med et andet selskab). Navnet skrives på alle billetterne i ordren. Der er ikke et navn pr. billet.

Der kan højst købes 50 billetter ad gangen. En Stripe-bestilling holder pladserne i ca. 30 minutter. Salget stopper, når der ikke er flere ledige pladser, også mens nogen er i gang med at betale.

## Billetterne

Efter betaling sendes én PDF til køberen. Der er én side pr. billet, og hver billet har sin egen QR-kode. Koden er et tilfældigt, ugætteligt tal. Den samme kode står også i grupper, så den kan tastes ind, hvis kameraet ikke virker.

Under **Billetter → Billetdesign** vælger I logo, overskrift, farver og om arrangement, dato, tid, køber, pris og QR-kode skal med. Forhåndsvisningen følger valgene.

## Indtjekning

1. Opret en bruger med rollen **Dørvagt**, hvis personalet ikke skal være administratorer.
2. Dørvagten logger ind og åbner **Billetter → Indtjekning**.
3. Scan QR-koden, eller indtast koden.

Den ene billet markeres som **fremmødt**. Scannes den igen, står der, at den allerede er brugt, og hvilke af køberens billetter der stadig er ledige.

Kameraet kræver HTTPS. Manuel indtastning virker også uden kamera.

## Skriv til købere

**Billetter → Skriv til købere** sender et emne og en besked til de e-mailadresser, der har en betalt ordre på det valgte arrangement. Antallet vises, før der sendes.

## Ordrer og deltagere

- **Ordrer** viser køber, arrangement, antal, beløb, status, bemærkning og betalingsmåde. Herfra kan en faktura markeres som betalt, en ubetalt ordre annulleres, og billetter kan sendes igen.
- **Eksporter PDF** laver en liste med køber, antal billetter og siddebemærkning.
- **Deltagere** er én række pr. billet. Køberens navn gentages på hver billet.

## Afinstallation

Deaktivering og sletning af pluginnet sletter ikke ordrer, billetter eller arrangementer.

Hvis alt skal væk, skal denne linje stå i `wp-config.php`, **før** pluginnet slettes:

```php
define( 'FHB_REMOVE_DATA', true );
```

Så fjernes tabeller, nøgler, rollen Dørvagt, arrangementerne og de salgssider, pluginnet har oprettet.

## Bevidste grænser

- Ingen WooCommerce.
- Ingen Quickpay, Pensopay eller ePay. Stripe dækker de almindelige kort- og MobilePay-betalinger, og faktura dækker det, I har aftalt.
- Ingen pladsplan og ingen navne pr. billet.
- Ingen automatisk refund fra Stripe. En betalt ordre annulleres ikke herfra; det gøres i Stripe.
- Ingen momsopgørelse eller elektronisk faktura.
- Højst 50 billetter pr. bestilling.
- Prisen er det beløb, køberen betaler. Der regnes ikke moms ud ved siden af.

Betalingsmåderne ligger bag et lille interface (`PaymentMethod`). Stripe og faktura er de to, der følger med. En anden metode kan registreres med handlingen `fhb_register_payment_methods`.
