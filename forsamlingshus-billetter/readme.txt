=== Forsamlingshus Billetter ===
Contributors: jens-ole-nielsen
Tags: tickets, events, stripe, mobilepay
Requires at least: 6.4
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 1.7.5
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

= 1.7.5 =
* På salgssiden står «At betale» til venstre, med det store beløb, og knappen «Gå til betaling» til højre. På en smal skærm kan beløbet stå over knappen.

= 1.7.4 =
* Billetten har én fast opsætning, og eksemplet viser den. Plakaten ligger foroven som hele billedet. Adgangsbillet, dato, tid, køber og pris står under billedet med tydelig tekst på den lyse bund. QR-kortet står til højre.
* Teksten ved QR-koden står inde i den hvide firkant, kun én gang, og er sort. Koden står på én linje, når der er plads.

= 1.7.3 =
* Ordre-PDF'en er en tæt oversigt med ordrenummer, arrangement, køber, e-mail, telefon, antal, beløb, betaling og bemærkning. Summen nederst følger rækkerne.
* Et beløb på 0 kr. uden rabatkode sættes til arrangementets pris gange antal. En gratis ordre med rabatkode bliver på 0 kr., og rabatten vises.
* Ved siden af Eksporter PDF ligger Eksporter CSV. Filen er UTF-8 med de samme kolonner og bruger semikolon, så Excel på dansk åbner den. Filteret for arrangement, status og søgning følges med.

= 1.7.2 =
* PDF-billetten bruger samme lærred som eksemplet under Billetdesign. Hele plakaten vises, med samme zoom og de samme felter. Den bliver ikke længere zoomet ind og beskåret.

= 1.7.1 =
* På salgssiden hedder kortbetalingen Betalingskort. Hjælpeteksten nævner ikke MobilePay. Stripe Checkout tilbyder stadig de metoder, der er slået til i Stripe.
* Beløbet «At betale» står ved knappen Gå til betaling. Det følger antal billetter og rabatkode.

= 1.7.0 =
* Under Billetter → Billetdesign står Zoom under baggrundsbilledet. Træk billedet i eksemplet for at flytte det. Tekst, logo og QR-kode bliver, hvor de er. Eksemplet og PDF-billetten bruger samme zoom og placering. En plakat bruger den samme placering.
* Første klik på Opdater installerer den nye version, også når det forrige tjek mod GitHub ikke fik svar. WordPress beholder den udgivelse, det allerede har fundet.

= 1.6.1 =
* Teksten ved QR-koden er altid sort, fordi fladen er hvid.
* Er QR-koden slået fra, står koden til manuel indtastning som almindelig tekst i samme farve som de andre felter. Den hvide flade følger kun QR-koden.

= 1.6.0 =
* Første klik på Opdater installerer den nye version. Et tjek, der ikke får svar fra GitHub, fjerner ikke den udgivelse, som Plugins-siden allerede viste.
* Under Billetter → Billetdesign kan tekst, logo og QR-kode slippes hvor som helst på billetten. De bliver der.
* Tekst og logo kan stå på en lys flade. Valget står under baggrundsbilledet. Som standard er fladen slået til, når billetten har et billede, og den kan slås fra. QR-koden har altid hvid baggrund.

= 1.5.0 =
* Under Billetter → Billetdesign kan I vælge baggrundsbillede og billetstørrelse. Skærmen viser, hvor mange pixels billedet skal være.
* Eksemplet er billetten. Træk tekst, logo og QR-kode, og gem design. PDF-billetten bruger de samme placeringer.
* Har arrangementet en plakat eller sit eget billetbillede, erstatter det baggrunden. Størrelse og placering følger designet.
* Teksten står på en lys flade, når der er et billede. QR-koden står på en hvid flade.

= 1.4.0 =
* Rabatkoder under Billetter → Rabatkoder: procent, et antal gratis billetter, eller hele ordren gratis. En kode kan gælde ét arrangement eller alle, og den kan have et maks. antal brug.
* Køberen kan skrive koden på salgssiden og se det nye beløb. En ugyldig, udløbet, opbrugt eller forkert kode ændrer ikke prisen.
* Er beløbet 0 kr., sendes billetterne med det samme, uden Stripe. Er beløbet over 0 men under 2,50 kr., oprettes ordren ikke.
* Ordren viser koden og rabatten. Nulstilling af et arrangement frigiver de koder, ordrerne brugte.
* Den indbyggede billet har ikke længere en grøn bjælke. Arrangementets billede fylder siden, og tekst, logo og QR-kode tegnes ovenpå. QR-koden står på en lys flade. Uden billede er billetten ensfarvet.
* Når bestillingen er gennemført, vises en dialog foran siden. Den siger, at billetterne er sendt, og minder om mappen med uønsket mail. Det gælder Stripe, faktura og gratis ordrer.
* På arrangementet kan I slå «hvor mange billetter der er tilbage» fra på salgssiden. Den er slået til som standard. Administrationen viser stadig solgte og ledige.

= 1.3.1 =
* Betaling starter i Stripe Checkout. Sessionen bruger ikke længere et parameter, Stripe afviser.
* Mangler Stripe-nøglen, eller afviser Stripe kaldet, vises en kort dansk forklaring i stedet for den generelle fejl.
* Opdateringer læses fra den offentlige GitHub-udgivelse uden token. Plugins-siden spørger igen efter to minutter, så en ny version ikke bliver skjult i timevis.

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
