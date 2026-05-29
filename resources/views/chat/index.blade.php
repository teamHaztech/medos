<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>WhatsApp Bot — MedOS</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center p-4" x-data="whatsappChat()" x-init="init()">

    <div class="w-full max-w-md mx-auto">
        {{-- Phone frame --}}
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden border border-slate-200" style="height: 90vh; max-height: 750px; display: flex; flex-direction: column;">

            {{-- WhatsApp header --}}
            <div class="bg-[#075e54] text-white px-4 py-3 flex items-center gap-3 flex-shrink-0">
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-sm">{{ $hospital->name ?? 'MedOS Hospital' }}</p>
                    <p class="text-xs text-white/70" x-text="typing ? 'typing...' : 'AI Assistant · Online'"></p>
                </div>
                <button @click="resetChat()" class="p-2 hover:bg-white/10 rounded-full" title="New Chat">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </button>
            </div>

            {{-- Chat background --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-2" style="background: #e5ddd5 url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAFklEQVQYV2P8+vXrfwY8gHHUCmIUAgBMbQcNlRqzfQAAAABJRU5ErkJggg==');"
                x-ref="chatArea">

                <template x-for="(msg, i) in messages" :key="i">
                    <div :class="msg.from === 'user' ? 'flex justify-end' : 'flex justify-start'">
                        <div :class="msg.from === 'user' ? 'bg-[#dcf8c6] rounded-tl-xl rounded-tr-sm rounded-bl-xl rounded-br-xl' : 'bg-white rounded-tl-sm rounded-tr-xl rounded-bl-xl rounded-br-xl'"
                            class="max-w-[85%] px-3 py-2 shadow-sm">
                            <p class="text-sm text-slate-800 whitespace-pre-wrap" x-html="formatMsg(msg.text)"></p>
                            <p class="text-[10px] text-slate-400 text-right mt-0.5" x-text="msg.time"></p>
                        </div>
                    </div>
                </template>

                {{-- Typing indicator --}}
                <div x-show="typing" class="flex justify-start">
                    <div class="bg-white rounded-xl px-4 py-3 shadow-sm">
                        <div class="flex gap-1">
                            <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                            <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                            <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Input bar --}}
            <div class="bg-white border-t border-slate-200 px-3 py-2 flex items-center gap-2 flex-shrink-0">
                <input type="text" x-model="input" @keydown.enter="sendMessage()" :disabled="typing"
                    class="flex-1 bg-slate-100 rounded-full px-4 py-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-[#075e54]"
                    placeholder="Type a message...">
                <button @click="sendMessage()" :disabled="!input.trim() || typing"
                    class="w-10 h-10 bg-[#075e54] hover:bg-[#064e46] text-white rounded-full flex items-center justify-center flex-shrink-0 disabled:opacity-50 transition-colors">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                </button>
            </div>
        </div>

        {{-- Label --}}
        <p class="text-center text-xs text-slate-400 mt-3">MedOS v{{ config('medos.version') }} · <a href="{{ route('kiosk.index') }}" class="text-blue-400 hover:underline">Kiosk</a> · <a href="{{ route('login') }}" class="text-blue-400 hover:underline">Login</a></p>
    </div>

    <script>
    function whatsappChat() {
        return {
            messages: [],
            input: '',
            typing: false,
            sessionId: '',
            phone: '',
            hospitalId: '',

            init() {
                this.sessionId = 'sess_' + Math.random().toString(36).substr(2, 9);
                this.hospitalId = new URLSearchParams(window.location.search).get('hospital_id') || @json($hospital->id ?? '');
                // Restore phone from previous conversation
                this.phone = localStorage.getItem('medos_chat_phone') || '';
                // Auto-start with greeting
                this.addBot("Hi! 👋 Send any message to start booking an appointment.\n\nमैं हिंदी में भी बात कर सकता हूं।\nيمكنني التحدث بالعربية أيضاً.");
            },

            async sendMessage() {
                const text = this.input.trim();
                if (!text || this.typing) return;

                this.addUser(text);
                this.input = '';
                this.typing = true;
                this.scrollDown();

                // Simulate slight delay like real typing
                await this.delay(500 + Math.random() * 1000);

                try {
                    const res = await fetch('{{ route("chat.send") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            message: text,
                            phone: this.phone,
                            session_id: this.sessionId,
                            hospital_id: this.hospitalId,
                        }),
                    });

                    const data = await res.json();

                    // Send replies one by one with typing delay
                    for (const reply of (data.replies || [])) {
                        this.typing = true;
                        this.scrollDown();
                        await this.delay(400 + reply.text.length * 15); // Longer messages = more "typing"
                        this.addBot(reply.text);
                        this.typing = false;
                        this.scrollDown();
                        await this.delay(300);
                    }

                    if (data.state?.phone) {
                        this.phone = data.state.phone;
                        localStorage.setItem('medos_chat_phone', data.state.phone);
                    }
                } catch (e) {
                    this.addBot("Sorry, something went wrong. Please try again.");
                }

                this.typing = false;
                this.scrollDown();
            },

            addUser(text) {
                this.messages.push({ from: 'user', text, time: this.now() });
            },

            addBot(text) {
                this.messages.push({ from: 'bot', text, time: this.now() });
            },

            formatMsg(text) {
                // WhatsApp-style formatting: *bold*, _italic_, ~strike~, ```code```
                return text
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/\*([^*]+)\*/g, '<strong>$1</strong>')
                    .replace(/_([^_]+)_/g, '<em class="text-slate-500">$1</em>')
                    .replace(/\n/g, '<br>');
            },

            now() {
                return new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            },

            delay(ms) {
                return new Promise(r => setTimeout(r, ms));
            },

            scrollDown() {
                this.$nextTick(() => {
                    const el = this.$refs.chatArea;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            },

            resetChat() {
                // Forget the remembered patient so a fresh conversation starts from
                // scratch (the bot will ask for phone, then name). Without this,
                // init() reloads the saved phone and recognises the returning patient.
                localStorage.removeItem('medos_chat_phone');
                this.messages = [];
                this.phone = '';
                this.sessionId = 'sess_' + Math.random().toString(36).substr(2, 9);
                this.init();
            },
        };
    }
    </script>
</body>
</html>
