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
        loadingOlder: false,
        hasOlder: true,
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

        groupedMessages() {
            return this.grouped;
        },

        get grouped() {
            const groups = [];
            let lastDate = null;
            let lastUser = null;

            this.messages.forEach((message) => {
                const dateLabel = this.dateLabel(message.created_at);

                if (dateLabel !== lastDate) {
                    groups.push({ type: 'date', id: `date-${dateLabel}-${message.id}`, label: dateLabel });
                    lastDate = dateLabel;
                    lastUser = null;
                }

                const grouped = lastUser === message.user.id;
                groups.push({ type: 'message', grouped, message });
                lastUser = message.user.id;
            });

            return groups;
        },

        dateLabel(value) {
            if (! value) {
                return '';
            }

            const date = new Date(value);
            const today = new Date();
            const yesterday = new Date();
            yesterday.setDate(today.getDate() - 1);

            if (date.toDateString() === today.toDateString()) {
                return 'Today';
            }

            if (date.toDateString() === yesterday.toDateString()) {
                return 'Yesterday';
            }

            return date.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
        },

        timeLabel(value) {
            if (! value) {
                return '';
            }

            return new Date(value).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
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

        firstMessageId() {
            if (this.messages.length === 0) {
                return 0;
            }

            return this.messages[0].id;
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

        async loadOlder() {
            if (! this.indexUrl || this.loadingOlder || ! this.hasOlder || this.messages.length === 0) {
                return;
            }

            this.loadingOlder = true;
            const scroller = this.$refs.scroller;
            const previousHeight = scroller ? scroller.scrollHeight : 0;

            try {
                const url = new URL(this.indexUrl, window.location.origin);
                url.searchParams.set('before_id', String(this.firstMessageId()));

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
                const older = payload.messages ?? [];

                if (older.length === 0) {
                    this.hasOlder = false;
                    return;
                }

                const existing = new Set(this.messages.map((message) => message.id));
                const incoming = older.filter((message) => ! existing.has(message.id));
                this.messages = incoming.concat(this.messages);

                this.$nextTick(() => {
                    if (scroller) {
                        scroller.scrollTop = scroller.scrollHeight - previousHeight;
                    }
                });
            } catch (error) {
                console.error(error);
            } finally {
                this.loadingOlder = false;
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

        onComposerKeydown(event) {
            if (event.key === 'Enter' && ! event.shiftKey) {
                event.preventDefault();
                this.send();
            }
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
