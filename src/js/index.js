// src/js/index.js - Главный файл экспортов
// Это позволит нам импортировать все нужные функции из одного места

// Экспортируем все утилиты
export * from './utils.js';

// Экспортируем функции корзины
export { 
    addToCart, 
    removeFromCart, 
    clearCart, 
    fetchCart,
    updateCartQuantity 
} from './cart.js';

// Экспортируем функции фильтров
export { 
    filterByBrandOrSeries, 
    applyFilters, 
    clearAllFilters,
    handleSearchInput,
    renderAppliedFilters,
    highlightFilteredWords
} from './filters.js';

// Экспортируем функции пагинации
export { 
    loadPage, 
    changeItemsPerPage, 
    changePage, 
    handlePageInputKeydown,
    updatePaginationDisplay
} from './pagination.js';

// Экспортируем функции сортировки
export { sortProducts } from './sort.js';

// Экспортируем функции рендеринга
export { 
    renderProductsTable, 
    copyText,
    bindSortableHeaders
} from './renderProducts.js';

// Экспортируем функции наличия товаров
export { loadAvailability } from './availability.js';

// Экспортируем функции спецификаций
export { createSpecification } from './specification.js';

// Экспортируем сервисы
export { productService } from './services/ProductService.js';
export { smartSearch } from './smartSearch.js';

// Экспортируем функцию поиска из search.js
export { fetchFromOpenSearch } from './search.js';

// ========================================
// src/js/config.js - Конфигурация приложения
// ========================================

// API endpoints
export const API_ENDPOINTS = {
    // Продукты
    PRODUCTS_SEARCH: '/api/products.php',
    PRODUCTS_GET: '/get_protop.php',
    PRODUCTS_AVAILABILITY: '/get_availability.php',
    
    // Корзина
    CART_ADD: '/cart/add',
    CART_REMOVE: '/cart/remove',
    CART_UPDATE: '/cart/update',
    CART_CLEAR: '/cart/clear',
    CART_GET: '/cart/json',
    
    // Спецификации
    SPEC_CREATE: '/specification/create',
    SPEC_LIST: '/specifications/json',
    
    // Пользователь
    USER_LOGIN: '/login',
    USER_LOGOUT: '/logout',
    USER_PROFILE: '/api/user/profile'
};

// Настройки по умолчанию
export const DEFAULTS = {
    ITEMS_PER_PAGE: 20,
    MAX_ITEMS_PER_PAGE: 100,
    SORT_COLUMN: 'name',
    SORT_DIRECTION: 'asc',
    DEBOUNCE_DELAY: 300,
    TOAST_DURATION: 3000,
    CACHE_TIMEOUT: 5 * 60 * 1000, // 5 минут
    MAX_CART_ITEMS: 100,
    MAX_QUANTITY: 9999
};

// Сообщения для пользователя
export const MESSAGES = {
    // Успешные операции
    CART_ADD_SUCCESS: 'Товар добавлен в корзину',
    CART_REMOVE_SUCCESS: 'Товар удален из корзины',
    CART_CLEAR_SUCCESS: 'Корзина очищена',
    CART_UPDATE_SUCCESS: 'Количество обновлено',
    SPEC_CREATE_SUCCESS: 'Спецификация создана',
    COPY_SUCCESS: 'Скопировано: ',
    
    // Ошибки
    CART_ADD_ERROR: 'Ошибка при добавлении в корзину',
    CART_REMOVE_ERROR: 'Ошибка при удалении из корзины',
    CART_CLEAR_ERROR: 'Ошибка при очистке корзины',
    CART_UPDATE_ERROR: 'Ошибка при обновлении количества',
    SPEC_CREATE_ERROR: 'Ошибка создания спецификации',
    COPY_ERROR: 'Не удалось скопировать',
    NETWORK_ERROR: 'Ошибка соединения с сервером',
    INVALID_DATA: 'Некорректные данные',
    SERVER_ERROR: 'Ошибка сервера',
    
    // Подтверждения
    CART_CLEAR_CONFIRM: 'Вы уверены, что хотите очистить корзину?',
    SPEC_CREATE_CONFIRM: 'Создать спецификацию из текущей корзины?'
};