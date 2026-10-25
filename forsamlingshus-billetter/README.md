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

Oversigten viser kolonnen **Solgt**. Tallet er betalte billetter. En faktura, der endnu ikke er markeret betalt, tæller ikke med. Den holder pladsen reserveret, så **Ledige** er lavere, indtil ordren betales eller annulleres.

Køberen oplyser navn, e-mail, telefon, antal og en valgfri bemærkning (for eksempel at de vil sidde sammen med et andet selskab). Navnet skrives på alle billetterne i ordren. Der er ikke et navn pr. billet.

Der kan højst købes 50 billetter ad gangen. En Stripe-bestilling holder pladserne i ca. 30 minutter. Salget stopper, når der ikke er flere ledige pladser, også mens nogen er i gang med at betale.

## Billetterne

Efter betaling sendes én PDF til køberen. Der er én side pr. billet, og hver billet har sin egen QR-kode. Koden er et tilfældigt, ugætteligt tal. Den samme kode står også i grupper, så den kan tastes ind, hvis kameraet ikke virker.

Under **Billetter → Billetdesign** vælger I logo, overskrift, farver og om arrangement, dato, tid, køber, pris og QR-kode skal med. Forhåndsvisningen følger valgene. I kan i stedet vælge en Elementor-skabelon. Så bruges den til PDF-billetten.

## Indtjekning

1. Opret en bruger med rollen **Dørvagt**, hvis personalet ikke skal være administratorer.
2. Dørvagten logger ind og åbner **Billetter → Indtjekning**.
3. Scan QR-koden, eller indtast koden.

Den ene billet markeres som **fremmødt**. Scannes den igen, står der, at den allerede er brugt, og hvilke af køberens billetter der stadig er ledige.

Kameraet kræver HTTPS. Manuel indtastning virker også uden kamera.

## Skriv til købere

**Billetter → Skriv til købere** sender et emne og en besked til de e-mailadresser, der har en betalt ordre på det valgte arrangement. Antallet vises, før der sendes.

## Ordrer og deltagere

- **Ordrer** viser køber, arrangement, antal, beløb, status, bemærkning og betalingsmåde. Herfra kan en faktura markeres som betalt, og en ubetalt ordre annulleres. På en betalt ordre, der allerede har billetter, kan I se den PDF, der blev sendt, og sende billetmailen igen.
- **Eksporter PDF** laver en liste med køber, antal billetter og siddebemærkning.
- **Deltagere** er én række pr. billet. Køberens navn gentages på hver billet.

## Afinstallation

Deaktivering og sletning af pluginnet sletter ikke ordrer, billetter eller arrangementer.

Hvis alt skal væk, skal denne linje stå i `wp-config.php`, **før** pluginnet slettes:

```php
define( 'FHB_REMOVE_DATA', true );
```

Så fjernes tabeller, nøgler, rollen Dørvagt, arrangementerne og de salgssider, pluginnet har oprettet.

## Elementor-skabelon til billetten

1. Installer og aktivér Elementor.
2. Opret en skabelon under **Skabeloner**. Hold den enkel: tekst, billeder og kortkoder. Et kompliceret layout bliver ikke altid ens, når det bliver til PDF.
3. Sæt disse kortkoder ind, der hvor oplysningerne skal stå: `[fhb_event_name]`, `[fhb_date]`, `[fhb_time]`, `[fhb_buyer]`, `[fhb_price]`, `[fhb_remark]`, `[fhb_qr]`. `[fhb_sequence]` skriver for eksempel "Billet 1 af 2". I kan også vælge de dynamiske tags i gruppen **Billet**. Arrangementets navn hedder `[fhb_event_name]`, fordi `[fhb_event]` allerede er salgssiden.
4. Gem skabelonen.
5. Gå til **Billetter → Billetdesign**, og vælg skabelonen i feltet **Elementor-skabelon**. Vælg **Indbygget design**, hvis logo, farver og felter på samme side skal bruges.

Hvis Elementor ikke er aktivt, eller skabelonen ikke kan tegnes, bruges det indbyggede design. Siden viser en kort besked, når Elementor mangler.

## MailPoet til billetmailen

1. Installer og aktivér MailPoet.
2. Lav den mail, køberen skal have. I teksten kan I bruge `[fhb_buyer]`, `[fhb_event_name]`, `[fhb_date]`, `[fhb_time]`, `[fhb_quantity]`, `[fhb_amount]`, `[fhb_order]` og `[fhb_remark]`. `[fhb_qr]` er QR-koden for den første billet. Hver side i PDF-filen har sin egen kode.
3. Gå til **Billetter → Indstillinger**, og vælg mailen under **MailPoet-mail**.

PDF-filen vedhæftes stadig. Selve afsendelsen sker med WordPress (`wp_mail`), ikke via MailPoets udsendelseskø. **Skriv til købere** er uændret og bruger den almindelige tekstmail.

Hvis MailPoet ikke er aktivt, eller mailen ikke kan laves, sendes den indbyggede tekstmail.

## Opdateringer

Pluginnet kan vise en almindelig opdatering under **Plugins**. Kilden er GitHub-udgivelser på [Jens-ole-nielsen/Billetter](https://github.com/Jens-ole-nielsen/Billetter).

Sådan udgives en ny version:

1. Sæt den samme version i plugin-headeren, i konstanten `FHB_VERSION` og i **Stable tag** i `readme.txt`.
2. Byg en zip, hvis rodmappe er `forsamlingshus-billetter/`. Filen `forsamlingshus-billetter.php` skal ligge i den mappe.
3. Opret en GitHub Release. Tagget skal være versionen, for eksempel `v1.2.0`.
4. Læg zip-filen op som et asset, der hedder præcis `forsamlingshus-billetter.zip`. Brug ikke den zip, GitHub selv laver af hele arkivet.
5. Udgiv releasen.

WordPress viser opdateringen, når versionsnummeret i udgivelsen er højere end det, der er installeret.

Arkivet er offentligt, så siden kan læse udgivelserne uden et token. Hvis arkivet er privat, gemmes et GitHub-token med læseadgang under **Billetter → Indstillinger**. Feltet er en adgangskode: et tomt felt beholder det gemte token, og afkrydsningen sletter det. Tokenet ligger kun i WordPress og ikke i plugin-filerne.

## Refundering

På en betalt ordre, der er betalt med Stripe, kan I refundere hele restbeløbet eller en del af det under **Billetter → Ordrer**. Beløbet sendes til Stripe som en refundering af betalingen fra Checkout. Ordren viser det refunderede beløb, om det er fuldt eller delvist, og Stripe-id'et.

Fuld refundering annullerer ordrens billetter, så de ikke kan tjekkes ind, og pladserne tæller ikke længere som solgt. Ved en delvis refundering skriver I et beløb i kroner, højst det der er tilbage, og hvor mange ubrugte billetter der skal annulleres. I kan skrive 0, hvis kun pengene skal tilbage. Fakturaordrer og ordrer, der ikke er betalt, kan ikke refunderes her. Hvis Stripe afviser beløbet, ændres ordren ikke.

## Bevidste grænser

- Ingen WooCommerce.
- Ingen Quickpay, Pensopay eller ePay. Stripe dækker de almindelige kort- og MobilePay-betalinger, og faktura dækker det, I har aftalt.
- Ingen pladsplan og ingen navne pr. billet.
- Ingen momsopgørelse eller elektronisk faktura.
- Højst 50 billetter pr. bestilling.
- Prisen er det beløb, køberen betaler. Der regnes ikke moms ud ved siden af.
- Elementor-skabelonen tegnes om til PDF med et enkelt HTML-værktøj. Avanceret layout fra Elementor kan derfor se anderledes ud på billetten.
- MailPoet bruges kun som brødtekst. Pluginnet sender selv mailen, så PDF-filen kan vedhæftes.

Betalingsmåderne ligger bag et lille interface (`PaymentMethod`). Stripe og faktura er de to, der følger med. En anden metode kan registreres med handlingen `fhb_register_payment_methods`.
