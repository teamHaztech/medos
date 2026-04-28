# WhatsApp Business API Setup — MedOS

## Step-by-Step Guide

### 1. Create Meta Developer Account
- Go to https://developers.facebook.com
- Create account or login
- Click **My Apps** → **Create App**
- Choose **Business** type
- Give it a name like "MedOS Hospital Bot"

### 2. Add WhatsApp Product
- In your app dashboard, click **Add Product**
- Find **WhatsApp** and click **Set Up**
- You'll see a test phone number and API setup

### 3. Get Your Credentials
From the WhatsApp section in your Meta app dashboard, copy:

| Setting | Where to find it |
|---------|-----------------|
| **Phone Number ID** | WhatsApp → API Setup → Phone number ID |
| **Access Token** | WhatsApp → API Setup → Temporary access token (or create permanent one) |
| **Business ID** | WhatsApp → API Setup → WhatsApp Business Account ID |

### 4. Update .env
Add these to your `/Users/haztech/medos/.env` file:

```env
WHATSAPP_PROVIDER=meta
WHATSAPP_META_TOKEN=your_access_token_here
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id_here
WHATSAPP_BUSINESS_ID=your_business_id_here
WHATSAPP_VERIFY_TOKEN=medos_webhook_secret_2026
```

### 5. Expose Your Local Server (for testing)
Meta needs a public HTTPS URL to send webhooks. Use ngrok:

```bash
# Install ngrok
brew install ngrok

# Start tunnel
ngrok http 8000
```

You'll get a URL like: `https://abc123.ngrok-free.app`

### 6. Configure Webhook in Meta
- Go to WhatsApp → Configuration → Webhook
- **Callback URL**: `https://abc123.ngrok-free.app/api/v1/whatsapp/webhook`
- **Verify Token**: `medos_webhook_secret_2026` (must match your .env)
- Click **Verify and Save**
- Subscribe to: `messages`

### 7. Test It
- Open WhatsApp on your phone
- Message the test phone number shown in Meta dashboard
- Type "Hi" — the bot should respond!

### 8. Use Your Own Number (Production)
To use your hospital's actual phone number:
- Go to WhatsApp → Phone Numbers → Add Phone Number
- Verify the number via SMS/call
- Submit for Meta review
- Once approved, update `WHATSAPP_PHONE_NUMBER_ID` in .env

---

## Architecture

```
Patient WhatsApp → Meta Cloud API → Your Server Webhook
                                         ↓
                                   /api/v1/whatsapp/webhook (POST)
                                         ↓
                                   WhatsAppWebhookController
                                         ↓
                                   ChatController (bot engine)
                                         ↓
                                   Response sent back via Meta API
                                         ↓
                                   Patient sees reply in WhatsApp
```

Same bot engine as the `/chat` simulator — multilingual, smart doctor matching, real booking.

## Quick Test Without Meta
Use the chat simulator at `http://localhost:8000/chat` — identical experience.

## Costs
- Meta WhatsApp Business API: First 1000 conversations/month FREE
- After that: ~$0.005-0.08 per conversation (varies by country)
- Template messages (outbound): ~₹0.50 per message (India)
