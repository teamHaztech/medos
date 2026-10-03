<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedOS API — Swagger Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            color: #0f172a;
        }
        .medos-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #fff;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #06b6d4;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .medos-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: inherit;
        }
        .medos-badge {
            background: #06b6d4;
            color: #0f172a;
            font-weight: 700;
            font-size: 0.75rem;
            padding: 0.2rem 0.55rem;
            border-radius: 9999px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .medos-title {
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .medos-nav {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .medos-link {
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: color 0.2s;
        }
        .medos-link:hover {
            color: #38bdf8;
        }
        .medos-btn {
            background: #0284c7;
            color: #fff;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 0.45rem 0.9rem;
            border-radius: 0.375rem;
            transition: background 0.2s;
        }
        .medos-btn:hover {
            background: #0369a1;
        }
        .swagger-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem 1rem 3rem 1rem;
        }
        .quick-banner {
            background: #e0f2fe;
            border: 1px solid #7dd3fc;
            color: #0369a1;
            padding: 0.75rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .quick-banner code {
            background: #bae6fd;
            padding: 0.15rem 0.4rem;
            border-radius: 0.25rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.82rem;
            color: #0c4a6e;
        }
        /* Custom tweaks for Swagger UI */
        .swagger-ui .topbar { display: none; }
        .swagger-ui .info { margin: 1.5rem 0 2rem 0; }
        .swagger-ui .info .title { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; color: #0f172a; }
        .swagger-ui .opblock.opblock-get { background: rgba(14, 165, 233, .05); border-color: #0ea5e9; }
        .swagger-ui .opblock.opblock-get .opblock-summary-method { background: #0ea5e9; }
        .swagger-ui .opblock.opblock-post { background: rgba(16, 185, 129, .05); border-color: #10b981; }
        .swagger-ui .opblock.opblock-post .opblock-summary-method { background: #10b981; }
        .swagger-ui .btn.authorize { color: #0284c7; border-color: #0284c7; }
        .swagger-ui .btn.authorize svg { fill: #0284c7; }
    </style>
</head>
<body>
    <header class="medos-header">
        <a href="/" class="medos-logo">
            <span class="medos-title">MedOS</span>
            <span class="medos-badge">API v1</span>
            <span style="color: #64748b; font-size: 0.875rem;">Interactive Swagger Specification</span>
        </a>
        <nav class="medos-nav">
            <a href="/api/v1/hospital-info" target="_blank" class="medos-link">Hospital Info API</a>
            <a href="/api/v1/openapi.json" target="_blank" class="medos-link">Raw OpenAPI JSON</a>
            <a href="/login" class="medos-btn">Open MedOS App</a>
        </nav>
    </header>

    <div class="swagger-container">
        <!-- Authorize Instructions Card -->
        <div style="background: #ffffff; border: 1px solid #cbd5e1; border-left: 4px solid #10b981; border-radius: 0.5rem; padding: 1.1rem 1.25rem; margin-bottom: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1.5rem; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 280px;">
                    <div style="font-weight: 700; color: #065f46; font-size: 0.95rem; margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.5rem;">
                        <span>🔐 How to Authorize in Swagger UI</span>
                        <span style="font-size: 0.75rem; background: #d1fae5; color: #065f46; padding: 0.15rem 0.5rem; border-radius: 9999px; font-weight: 600;">Sanctum Bearer Token</span>
                    </div>
                    <p style="margin: 0 0 0.5rem 0; font-size: 0.85rem; color: #334155; line-height: 1.45;">
                        Protected endpoints (booking, patient lookup, customer) require a Bearer token. To authenticate:
                    </p>
                    <ol style="margin: 0; padding-left: 1.25rem; font-size: 0.825rem; color: #475569; line-height: 1.6;">
                        <li>Under the <strong>Auth</strong> section below, open <code>POST /api/v1/auth/login</code> and click <em>Try it out</em>.</li>
                        <li>Execute using <code>admin@haztech.in</code> / <code>password123</code> and copy the <code>token</code> string from the JSON response.</li>
                        <li>Click the green <strong>"Authorize 🔓"</strong> button on the right.</li>
                        <li>Paste your token into the <strong>BearerAuth</strong> input field and click <strong>Authorize</strong>.</li>
                        <li>(Optional) Enter <code>city-care</code> in <strong>HospitalHeader (X-Hospital-ID)</strong> for multi-tenant hospital routing.</li>
                    </ol>
                </div>
                <div style="background: #f8fafc; padding: 0.85rem 1rem; border-radius: 0.375rem; font-size: 0.8rem; border: 1px solid #e2e8f0; min-width: 240px;">
                    <div style="font-weight: 700; color: #1e293b; margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.35rem;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; display: inline-block;"></span>
                        Quick Demo Credentials
                    </div>
                    <div style="margin-bottom: 0.2rem; color: #475569;">Email: <code style="background: #e2e8f0; padding: 0.1rem 0.3rem; border-radius: 0.2rem;">admin@haztech.in</code></div>
                    <div style="margin-bottom: 0.2rem; color: #475569;">Password: <code style="background: #e2e8f0; padding: 0.1rem 0.3rem; border-radius: 0.2rem;">password123</code></div>
                    <div style="color: #64748b; font-size: 0.77rem; margin-top: 0.35rem;">Default Hospital: <code>city-care</code></div>
                </div>
            </div>
        </div>

        <div class="quick-banner">
            <div>
                <strong>Multi-Tenant Targeting:</strong> Send <code>X-Hospital-ID: city-care</code> or query param <code>?hospital=city-care</code> to test specific hospital endpoints.
            </div>
            <div>
                <span>Hospital Info: <code>GET /api/v1/hospital-info?hospital=city-care</code></span>
            </div>
        </div>

        <div id="swagger-ui"></div>
    </div>

    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        window.onload = () => {
            window.ui = SwaggerUIBundle({
                url: '/swagger.json',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIBundle.SwaggerUIStandalonePreset
                ],
                layout: "BaseLayout",
                defaultModelsExpandDepth: 2,
                defaultModelExpandDepth: 2,
                displayRequestDuration: true,
                docExpansion: "list",
                filter: true,
                showExtensions: true,
                showCommonExtensions: true
            });
        };
    </script>
</body>
</html>
