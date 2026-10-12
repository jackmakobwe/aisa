import Alpine from '@alpinejs/csp';
import { renderIcons } from './icons';
import { quickExit } from './components/quick-exit';
import { chat } from './components/chat';
import { launcher } from './components/launcher';
import { draftKeeper, copyCode, menu, consentToggle, savedResponses } from './components/ui';

Alpine.data('quickExit', quickExit);
Alpine.data('chat', chat);
Alpine.data('draftKeeper', draftKeeper);
Alpine.data('copyCode', copyCode);
Alpine.data('menu', menu);
Alpine.data('launcher', launcher);
Alpine.data('consentToggle', consentToggle);
Alpine.data('savedResponses', savedResponses);

window.Alpine = Alpine;
window.aisaRenderIcons = renderIcons;

// Forms marked data-confirm ask before submitting (destructive admin actions).
document.addEventListener('submit', (event) => {
    const message = event.target.getAttribute?.('data-confirm');
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

document.addEventListener('DOMContentLoaded', renderIcons);
Alpine.start();
