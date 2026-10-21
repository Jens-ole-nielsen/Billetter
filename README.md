# Eventbilletter

WordPress-plugin til at sælge eventbilletter med **Stripe Checkout** — uden WooCommerce.

## Hurtig start

1. Kopiér mappen `eventbilletter/` til `wp-content/plugins/`
2. Aktivér **Eventbilletter** i WordPress
3. Gå til **Events → Indstillinger** og indsæt Stripe test-/live-nøgler
4. Opret en Stripe-webhook til:
   `https://dit-domæne.dk/wp-json/eventbilletter/v1/stripe-webhook`
5. Opret et event under **Events**, tilføj billettyper, og publicér

Billetformularen vises automatisk på event-siden, eller via shortcode:

```
[eb_tickets]
[eb_tickets id="123"]
[eb_checkout_status]
```

## Funktioner

| Område | Detalje |
|--------|---------|
| Events | CPT med dato, tid, sted, billetsalg åben/lukket |
| Billettyper | Navn, beskrivelse, pris, kapacitet, solgt-tæller |
| Betaling | Stripe Checkout Session (redirect) |
| Ordrer | Admin-liste med status, kunde, billetkoder |
| E-mail | Bekræftelse til køber + notifikation til admin |
| Kapacitet | Reserveres ved checkout, frigives ved annullering/udløb/refund |

## Teknisk

- Ingen WooCommerce, ingen Composer — Stripe kaldes via WordPress HTTP API
- Webhook-signatur verificeres med HMAC (Stripe-Signature)
- Valuta standard: DKK (ændres under indstillinger)

## Mappe-struktur

```
eventbilletter/
├── eventbilletter.php      # Plugin bootstrap
├── includes/               # CPT, tickets, orders, Stripe, checkout, webhooks, emails
├── admin/                  # Indstillinger, meta boxes, ordrevisning
├── public/                 # Frontend assets + content filters
├── templates/              # Billetformular + status
└── assets/                 # CSS/JS
```

## Stripe Dashboard

Under **Developers → Webhooks** skal du lytte til:

- `checkout.session.completed`
- `checkout.session.expired`
- `charge.refunded`

Brug testnøgler (`sk_test_…` / `pk_test_…`) indtil I er klar til live salg.
