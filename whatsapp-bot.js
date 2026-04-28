/**
 * MedOS WhatsApp Bot — uses whatsapp-web.js
 *
 * Run: node whatsapp-bot.js
 * Scan the QR code with your hospital WhatsApp number
 * Patients message that number → bot handles booking
 */

import pkg from 'whatsapp-web.js';
const { Client, LocalAuth } = pkg;
import qrcode from 'qrcode-terminal';
import http from 'http';

// ---------------------------------------------------------------
// Config
// ---------------------------------------------------------------

const MEDOS_URL = process.env.MEDOS_URL || 'http://localhost:8000';
const BOT_NAME = 'MedOS Hospital Bot';

// ---------------------------------------------------------------
// WhatsApp Client
// ---------------------------------------------------------------

const client = new Client({
    authStrategy: new LocalAuth({ dataPath: '.wwebjs_auth' }),
    puppeteer: {
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
    },
});

// Session store (in-memory — phone → session_id)
const sessions = {};

// ---------------------------------------------------------------
// Events
// ---------------------------------------------------------------

client.on('qr', (qr) => {
    console.log('\n╔══════════════════════════════════════╗');
    console.log('║   MedOS WhatsApp Bot                 ║');
    console.log('║   Scan this QR code with WhatsApp    ║');
    console.log('╚══════════════════════════════════════╝\n');
    qrcode.generate(qr, { small: true });
    console.log('\nWaiting for scan...\n');
});

client.on('ready', () => {
    console.log('╔══════════════════════════════════════╗');
    console.log('║   ✅ Bot is READY and listening!      ║');
    console.log('║   Patients can now message this      ║');
    console.log('║   number to book appointments.       ║');
    console.log('╚══════════════════════════════════════╝');
    console.log(`\nConnected as: ${client.info?.pushname || 'Unknown'}`);
    console.log(`Phone: ${client.info?.wid?.user || 'Unknown'}`);
    console.log(`MedOS Server: ${MEDOS_URL}`);
    console.log(`Time: ${new Date().toLocaleString()}\n`);
});

client.on('authenticated', () => {
    console.log('✅ Authenticated successfully');
});

client.on('auth_failure', (msg) => {
    console.error('❌ Authentication failed:', msg);
});

client.on('disconnected', (reason) => {
    console.log('❌ Disconnected:', reason);
    console.log('Restarting in 5 seconds...');
    setTimeout(() => client.initialize(), 5000);
});

// ---------------------------------------------------------------
// Message Handler
// ---------------------------------------------------------------

client.on('message', async (msg) => {
    // Ignore group messages, status updates, own messages
    if (msg.from.includes('@g.us')) return;
    if (msg.from === 'status@broadcast') return;
    if (msg.fromMe) return;

    const phone = msg.from.replace('@c.us', '');
    const text = msg.body?.trim();

    if (!text) return;

    // Clean phone: 919876543210 → 9876543210
    let cleanPhone = phone.replace(/^91/, '');
    if (cleanPhone.length > 10) cleanPhone = cleanPhone.slice(-10);

    console.log(`📩 [${cleanPhone}] ${text}`);

    // Get or create session
    if (!sessions[cleanPhone]) {
        sessions[cleanPhone] = 'wa_' + cleanPhone;
    }
    const sessionId = sessions[cleanPhone];

    try {
        // Send to MedOS bot engine
        const replies = await sendToMedOS(text, cleanPhone, sessionId);

        // Send each reply with a small delay (feels natural)
        for (const reply of replies) {
            // Show "typing" indicator
            const chat = await msg.getChat();
            await chat.sendStateTyping();

            // Delay based on message length (min 1s, max 3s)
            const delay = Math.min(3000, Math.max(1000, reply.text.length * 20));
            await sleep(delay);

            // Send reply
            await client.sendMessage(msg.from, reply.text);
            console.log(`📤 [${cleanPhone}] ${reply.text.substring(0, 80)}...`);
        }
    } catch (err) {
        console.error(`❌ Error processing message from ${cleanPhone}:`, err.message);

        // Send fallback message
        try {
            await client.sendMessage(msg.from,
                'Sorry, I\'m having trouble right now. Please try again in a moment or call the hospital directly. 🙏\n\n' +
                'क्षमा करें, अभी कुछ समस्या हो रही है। कृपया थोड़ी देर बाद फिर कोशिश करें।'
            );
        } catch (e) {}
    }
});

// ---------------------------------------------------------------
// MedOS API Communication
// ---------------------------------------------------------------

async function sendToMedOS(message, phone, sessionId) {
    return new Promise((resolve, reject) => {
        const postData = JSON.stringify({
            message: message,
            phone: phone,
            session_id: sessionId,
        });

        // First get CSRF token by hitting the chat page
        const csrfReq = http.get(`${MEDOS_URL}/chat`, (csrfRes) => {
            let csrfHtml = '';
            csrfRes.on('data', (chunk) => csrfHtml += chunk);
            csrfRes.on('end', () => {
                // Extract CSRF token
                const csrfMatch = csrfHtml.match(/csrf-token" content="([^"]+)"/);
                const csrf = csrfMatch ? csrfMatch[1] : '';

                // Extract session cookie
                const cookies = csrfRes.headers['set-cookie'] || [];
                const cookieStr = cookies.map(c => c.split(';')[0]).join('; ');

                // Now POST the message
                const url = new URL(`${MEDOS_URL}/chat/send`);
                const options = {
                    hostname: url.hostname,
                    port: url.port,
                    path: url.pathname,
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Cookie': cookieStr,
                        'Content-Length': Buffer.byteLength(postData),
                    },
                };

                const req = http.request(options, (res) => {
                    let body = '';
                    res.on('data', (chunk) => body += chunk);
                    res.on('end', () => {
                        try {
                            const data = JSON.parse(body);
                            resolve(data.replies || [{ text: 'No response from server.' }]);
                        } catch (e) {
                            reject(new Error('Invalid JSON from MedOS: ' + body.substring(0, 200)));
                        }
                    });
                });

                req.on('error', reject);
                req.write(postData);
                req.end();
            });
        });

        csrfReq.on('error', reject);
    });
}

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// ---------------------------------------------------------------
// Graceful Shutdown
// ---------------------------------------------------------------

process.on('SIGINT', async () => {
    console.log('\n\nShutting down bot...');
    await client.destroy();
    process.exit(0);
});

// ---------------------------------------------------------------
// Start
// ---------------------------------------------------------------

console.log('Starting MedOS WhatsApp Bot...');
console.log(`Connecting to MedOS at: ${MEDOS_URL}\n`);
client.initialize();
