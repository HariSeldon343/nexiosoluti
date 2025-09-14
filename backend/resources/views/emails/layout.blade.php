<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $subject ?? 'NexioSolution' }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset styles */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }

        /* Remove default styling */
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; }
        a[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: none !important;
            font-size: inherit !important;
            font-family: inherit !important;
            font-weight: inherit !important;
            line-height: inherit !important;
        }

        /* Mobile styles */
        @media screen and (max-width: 600px) {
            .mobile-hide { display: none !important; }
            .mobile-center { text-align: center !important; }
            .mobile-padding { padding: 20px !important; }
            table.responsive-table { width: 100% !important; }
            td.responsive-td { display: block !important; width: 100% !important; }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .dark-mode-bg { background-color: #1a1a1a !important; }
            .dark-mode-text { color: #ffffff !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; word-spacing: normal; background-color: {{ config('mail.branding.background_color', '#f7fafc') }};">
    <div role="article" aria-roledescription="email" lang="{{ app()->getLocale() }}" style="text-size-adjust: 100%; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
        <table role="presentation" style="width: 100%; border: none; border-spacing: 0;">
            <tr>
                <td align="center" style="padding: 0;">
                    <!--[if mso]>
                    <table role="presentation" align="center" style="width: 600px;">
                    <tr>
                    <td>
                    <![endif]-->

                    <table role="presentation" style="width: 94%; max-width: 600px; border: none; border-spacing: 0; text-align: left; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 16px; line-height: 24px; color: {{ config('mail.branding.text_color', '#374151') }};">

                        <!-- Header -->
                        <tr>
                            <td style="padding: 40px 30px 30px 30px; text-align: center;">
                                <a href="{{ config('app.url') }}" style="text-decoration: none;">
                                    @if(config('mail.branding.logo'))
                                        <img src="{{ config('mail.branding.logo') }}"
                                             alt="{{ config('app.name') }}"
                                             style="width: {{ config('mail.branding.logo_width', 200) }}px; max-width: 80%; height: auto; border: none; text-decoration: none; color: {{ config('mail.branding.primary_color', '#4F46E5') }};">
                                    @else
                                        <h1 style="margin: 0; font-size: 32px; font-weight: bold; color: {{ config('mail.branding.primary_color', '#4F46E5') }};">
                                            {{ config('app.name', 'NexioSolution') }}
                                        </h1>
                                    @endif
                                </a>
                            </td>
                        </tr>

                        <!-- Main Content -->
                        <tr>
                            <td style="padding: 0 30px; background-color: #ffffff; border-radius: 8px;">
                                <table role="presentation" style="width: 100%; border: none; border-spacing: 0;">
                                    @yield('content')
                                </table>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style="padding: 30px; text-align: center; font-size: 12px; color: #6b7280;">

                                <!-- Social Links -->
                                @if(config('mail.branding.social_links'))
                                    <table role="presentation" style="width: auto; border: none; border-spacing: 0; margin: 0 auto 20px;">
                                        <tr>
                                            @if(config('mail.branding.social_links.facebook'))
                                                <td style="padding: 0 5px;">
                                                    <a href="{{ config('mail.branding.social_links.facebook') }}" style="text-decoration: none;">
                                                        <img src="{{ asset('images/social/facebook.png') }}" alt="Facebook" width="32" height="32" style="display: block; border: 0;">
                                                    </a>
                                                </td>
                                            @endif

                                            @if(config('mail.branding.social_links.twitter'))
                                                <td style="padding: 0 5px;">
                                                    <a href="{{ config('mail.branding.social_links.twitter') }}" style="text-decoration: none;">
                                                        <img src="{{ asset('images/social/twitter.png') }}" alt="Twitter" width="32" height="32" style="display: block; border: 0;">
                                                    </a>
                                                </td>
                                            @endif

                                            @if(config('mail.branding.social_links.linkedin'))
                                                <td style="padding: 0 5px;">
                                                    <a href="{{ config('mail.branding.social_links.linkedin') }}" style="text-decoration: none;">
                                                        <img src="{{ asset('images/social/linkedin.png') }}" alt="LinkedIn" width="32" height="32" style="display: block; border: 0;">
                                                    </a>
                                                </td>
                                            @endif

                                            @if(config('mail.branding.social_links.instagram'))
                                                <td style="padding: 0 5px;">
                                                    <a href="{{ config('mail.branding.social_links.instagram') }}" style="text-decoration: none;">
                                                        <img src="{{ asset('images/social/instagram.png') }}" alt="Instagram" width="32" height="32" style="display: block; border: 0;">
                                                    </a>
                                                </td>
                                            @endif
                                        </tr>
                                    </table>
                                @endif

                                <!-- Footer Text -->
                                <p style="margin: 0 0 10px; line-height: 18px;">
                                    {{ config('mail.branding.footer_text', '© ' . date('Y') . ' NexioSolution. Tutti i diritti riservati.') }}
                                </p>

                                <!-- Unsubscribe Link -->
                                @if(isset($unsubscribe_url))
                                    <p style="margin: 0; line-height: 18px;">
                                        <a href="{{ $unsubscribe_url }}" style="color: {{ config('mail.branding.link_color', '#4F46E5') }}; text-decoration: underline;">
                                            Annulla iscrizione
                                        </a>
                                        @if(isset($preferences_url))
                                            | <a href="{{ $preferences_url }}" style="color: {{ config('mail.branding.link_color', '#4F46E5') }}; text-decoration: underline;">
                                                Gestisci preferenze
                                            </a>
                                        @endif
                                    </p>
                                @endif

                                <!-- Company Address -->
                                @if(isset($company_address))
                                    <p style="margin: 10px 0 0; line-height: 18px; color: #9ca3af;">
                                        {{ $company_address }}
                                    </p>
                                @endif

                                <!-- Email Tracking Pixel -->
                                @if(config('mail.tracking.enabled') && config('mail.tracking.opens') && isset($tracking_pixel))
                                    <img src="{{ $tracking_pixel }}" alt="" width="1" height="1" style="display: block; border: 0;">
                                @endif
                            </td>
                        </tr>

                    </table>

                    <!--[if mso]>
                    </td>
                    </tr>
                    </table>
                    <![endif]-->
                </td>
            </tr>
        </table>
    </div>
</body>
</html>