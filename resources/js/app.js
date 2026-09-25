import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import { registerTheme } from './theme';
import { registerPalette } from './palette';
import { registerUi } from './ui';
import { registerPwa } from './pwa';
import { registerOutbox } from './outbox';

Alpine.plugin(focus);
registerTheme(Alpine);
registerPalette(Alpine);
registerUi(Alpine);
registerPwa(Alpine);
registerOutbox(Alpine);

window.Alpine = Alpine;
Alpine.start();
