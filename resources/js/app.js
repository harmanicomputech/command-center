import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import { registerTheme } from './theme';
import { registerPalette } from './palette';
import { registerUi } from './ui';
import { registerPwa } from './pwa';

Alpine.plugin(focus);
registerTheme(Alpine);
registerPalette(Alpine);
registerUi(Alpine);
registerPwa(Alpine);

window.Alpine = Alpine;
Alpine.start();
