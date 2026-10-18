import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import registerCart from './cart';
import registerShopFront from './shop-front';
import registerCatalogMonitor from './catalog-monitor';
import registerPromoBoard from './promo-board';
import registerPromotionForm from './promotion-form';
import registerSeller from './seller';
import { taskStatus, notificationBell } from './tasks';

Alpine.plugin(collapse);
registerCart(Alpine);
registerShopFront(Alpine);
registerCatalogMonitor(Alpine);
registerPromoBoard(Alpine);
registerPromotionForm(Alpine);
registerSeller(Alpine);
Alpine.data('taskStatus', taskStatus);
Alpine.data('notificationBell', notificationBell);

window.Alpine = Alpine;
Alpine.start();
