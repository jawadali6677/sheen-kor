import Alpine from 'alpinejs';
import './echo';
import { registerChat } from './chat';
import { registerNotifications } from './notifications';
import { registerQualifiedPostViews } from './qualified-post-views';
import { registerSheenUi } from './sheen-ui';

registerChat(Alpine);
registerNotifications(Alpine);
registerSheenUi(Alpine);
registerQualifiedPostViews();

window.Alpine = Alpine;

Alpine.start();
