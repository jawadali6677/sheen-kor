export function registerChat(Alpine) {
    Alpine.data('chatThread', (config) => ({
        conversationId: config.conversationId,
        currentUserId: config.currentUserId,
        messages: Array.isArray(config.messages) ? config.messages : Object.values(config.messages ?? {}),
        storeUrl: config.storeUrl,
        indexUrl: config.indexUrl,
        csrfToken: config.csrfToken,
        body: '',
        sending: false,
        pollTimer: null,

        init() {
            this.scrollToBottom();
            this.subscribeToEcho();
            this.pollTimer = window.setInterval(() => {
                this.fetchNewMessages();
            }, 2000);
        },

        destroy() {
            if (this.pollTimer) {
                window.clearInterval(this.pollTimer);
            }
        },

        subscribeToEcho() {
            if (! window.Echo) {
                return;
            }

            window.Echo.private(`conversations.${this.conversationId}`)
                .listen('.MessageSent', (message) => {
                    this.appendMessage(message);
                });
        },

        lastMessageId() {
            if (this.messages.length === 0) {
                return 0;
            }

            return this.messages[this.messages.length - 1].id;
        },

        async fetchNewMessages() {
            if (! this.indexUrl) {
                return;
            }

            try {
                const url = new URL(this.indexUrl, window.location.origin);
                url.searchParams.set('after_id', String(this.lastMessageId()));

                const response = await fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });

                if (! response.ok) {
                    return;
                }

                const payload = await response.json();

                (payload.messages ?? []).forEach((message) => {
                    this.appendMessage(message);
                });
            } catch (error) {
                console.error(error);
            }
        },

        appendMessage(message) {
            if (! message?.id || this.messages.some((existing) => existing.id === message.id)) {
                return;
            }

            this.messages.push(message);
            this.$nextTick(() => this.scrollToBottom());
        },

        scrollToBottom() {
            if (! this.$refs.scroller) {
                return;
            }

            this.$refs.scroller.scrollTop = this.$refs.scroller.scrollHeight;
        },

        async send() {
            const body = this.body.trim();

            if (body === '' || this.sending) {
                return;
            }

            this.sending = true;

            try {
                const headers = {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                };

                const socketId = window.Echo?.socketId();

                if (socketId) {
                    headers['X-Socket-ID'] = socketId;
                }

                const response = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({ body }),
                });

                const payload = await response.json();

                if (! response.ok || ! payload.chat_message) {
                    return;
                }

                this.body = '';
                this.appendMessage(payload.chat_message);
            } catch (error) {
                console.error(error);
            } finally {
                this.sending = false;
            }
        },
    }));
}
