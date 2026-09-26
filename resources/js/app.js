import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import { registerTheme } from './theme';
import { registerPalette } from './palette';
import { registerUi } from './ui';
import { registerPwa } from './pwa';
import { registerOutbox } from './outbox';
import { registerPhotoField } from './photos';
import { registerFieldForms } from './field-forms';

Alpine.plugin(focus);
registerTheme(Alpine);
registerPalette(Alpine);
registerUi(Alpine);
registerPwa(Alpine);
registerOutbox(Alpine);
registerPhotoField(Alpine);
registerFieldForms(Alpine);

window.Alpine = Alpine;
Alpine.start();
