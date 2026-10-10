import { renderIcons } from '../icons';

/**
 * Conversation composer. Works as a plain form without JavaScript; with
 * JavaScript it posts with fetch and appends the server-rendered messages.
 */
export function chat() {
    return {
        sending: false,
        error: '',
        canRetry: false,
        suggestHuman: false,
        length: 0,
        max: 4000,
        urls: {},
        text: {},

        init() {
            // Read configuration once, from the component root.
            const data = this.$el.dataset;
            this.max = Number(data.max || 4000);
            this.canRetry = data.canRetry === '1';
            this.urls = { retry: data.retryUrl, check: data.checkUrl };
            this.text = { generic: data.genericError, network: data.networkError };
            this.scrollToEnd();
        },

        get isIdle() {
            return !this.sending;
        },

        get hasError() {
            return this.error !== '';
        },

        get counter() {
            return `${this.length} / ${this.max}`;
        },

        get counterClass() {
            return this.length > this.max * 0.9 ? 'text-clay font-semibold' : '';
        },

        onInput(event) {
            this.length = event.target.value.length;
        },

        onKeydown(event) {
            // Enter sends; Shift+Enter makes a new line. Never on touch keyboards.
            const coarse = window.matchMedia('(pointer: coarse)').matches;
            if (event.key === 'Enter' && !event.shiftKey && !coarse && !event.isComposing) {
                event.preventDefault();
                this.submit();
            }
        },

        async submit() {
            const field = this.$refs.input;
            const text = field.value.trim();
            if (this.sending || text.length < 2) {
                field.focus();
                return;
            }

            const ok = await this.post(this.$refs.form.action, new FormData(this.$refs.form));
            if (ok) {
                field.value = '';
                this.length = 0;
            }
            field.focus();
        },

        async retry() {
            await this.post(this.urls.retry, new FormData());
        },

        async post(url, body) {
            this.sending = true;
            this.error = '';
            this.scrollToEnd();

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    body,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                });

                if (response.status === 401 || response.status === 419) {
                    window.location.href = this.urls.check;
                    return false;
                }

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    this.error = data.message || this.text.generic;
                    if (data.errors) {
                        this.error = Object.values(data.errors).flat().join(' ');
                    }
                    return false;
                }

                if (data.html) {
                    this.$refs.list.insertAdjacentHTML('beforeend', data.html);
                    renderIcons();
                }
                this.error = data.error || '';
                this.canRetry = Boolean(data.can_retry);
                this.suggestHuman = this.suggestHuman || Boolean(data.suggest_human);
                return true;
            } catch (e) {
                this.error = this.text.network;
                return false;
            } finally {
                this.sending = false;
                this.$nextTick(() => this.scrollToEnd());
            }
        },

        scrollToEnd() {
            const list = this.$refs.list;
            if (list) {
                list.lastElementChild?.scrollIntoView({ block: 'end', behavior: 'smooth' });
            }
        },
    };
}
