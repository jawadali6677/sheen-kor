<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Local Stripe Checkout

Paid orders are marked paid in two ways:

- Stripe redirects the member to `/orders/{order}?checkout=success&session_id={CHECKOUT_SESSION_ID}`. That page retrieves the Checkout Session and, when Stripe reports `paid` or `no_payment_required` for that same order, marks the order paid.
- `POST /stripe/webhook` (Laravel Cashier) handles `checkout.session.completed` and `checkout.session.async_payment_succeeded`. CSRF is already disabled for `stripe/*`. Signature verification uses `STRIPE_WEBHOOK_SECRET`.

The return URL still marks the order paid when the webhook is delayed or never forwarded, as long as `session_id` is in the URL. The webhook alone still marks the order paid when the member never returns.

`stripe listen` only forwards events when you give it this app's webhook URL. From the project root, with the app on port 8000:

```bash
stripe listen --forward-to http://127.0.0.1:8000/stripe/webhook
```

The CLI prints a signing secret (`whsec_...`) after `Ready!`. Put that exact value in `.env`:

```bash
STRIPE_WEBHOOK_SECRET=whsec_...
php artisan config:clear
```

`STRIPE_KEY` and `STRIPE_SECRET` must be test keys from the same Stripe account the CLI is logged into. The CLI secret is not the signing secret of a webhook endpoint created in the Dashboard. Use the Dashboard secret only when that endpoint is what receives the events.

If the CLI stays on `Ready!` and never prints a forwarded event, it is not receiving events for this account (Checkout ran before the listener started, or the secret keys belong to another account). Change the port in `--forward-to` if `php artisan serve` is not on 8000.

To forward only the events this app handles:

```bash
stripe listen \
  --events checkout.session.completed,checkout.session.async_payment_succeeded,checkout.session.expired,checkout.session.async_payment_failed,payment_intent.payment_failed \
  --forward-to http://127.0.0.1:8000/stripe/webhook
```

Fulfillment and skip reasons are written to the Laravel log (`Stripe checkout return fulfilled`, `Stripe checkout return skipped`, `Stripe webhook fulfilled`, `Stripe webhook skipped`). The admin order page shows the Stripe Checkout session id stored on the payment.
