import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import registerCart from './cart';
import registerShopFront from './shop-front';

Alpine.plugin(collapse);
registerCart(Alpine);
registerShopFront(Alpine);

window.Alpine = Alpine;

Alpine.start();
