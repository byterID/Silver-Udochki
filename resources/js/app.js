import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import registerCart from './cart';
import registerShopFront from './shop-front';
import registerPromoBoard from './promo-board';
import registerSeller from './seller';

Alpine.plugin(collapse);
registerCart(Alpine);
registerShopFront(Alpine);
registerPromoBoard(Alpine);
registerSeller(Alpine);

window.Alpine = Alpine;

Alpine.start();
