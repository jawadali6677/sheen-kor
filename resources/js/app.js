import Alpine from 'alpinejs';
import './echo';
import { registerChat } from './chat';
import { registerSheenUi } from './sheen-ui';

registerChat(Alpine);
registerSheenUi(Alpine);

window.Alpine = Alpine;

Alpine.start();
