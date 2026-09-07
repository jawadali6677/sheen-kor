import Alpine from 'alpinejs';
import './echo';
import { registerChat } from './chat';

registerChat(Alpine);

window.Alpine = Alpine;

Alpine.start();
