import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import registerCart from './cart';

Alpine.plugin(collapse);
registerCart(Alpine);

window.Alpine = Alpine;

Alpine.start();
