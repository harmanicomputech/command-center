import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import { registerTheme } from './theme';
import { registerPalette } from './palette';
import { registerUi } from './ui';
import { registerPwa } from './pwa';
import { registerOutbox } from './outbox';
import { registerPhotoField } from './photos';
import { registerFieldForms } from './field-forms';
import { registerPush } from './push';
import { registerEngage } from './engage';

Alpine.plugin(focus);
registerTheme(Alpine);
registerPalette(Alpine);
registerUi(Alpine);
registerPwa(Alpine);
registerOutbox(Alpine);
registerPhotoField(Alpine);
registerFieldForms(Alpine);
registerPush(Alpine);
registerEngage(Alpine);

window.Alpine = Alpine;
Alpine.start();
