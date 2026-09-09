import Alpine from 'alpinejs';
import './echo';
import { registerChat } from './chat';
import { registerNotifications } from './notifications';
import { registerSheenUi } from './sheen-ui';

registerChat(Alpine);
registerNotifications(Alpine);
registerSheenUi(Alpine);

window.Alpine = Alpine;

Alpine.start();
