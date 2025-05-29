/**
 * Валидация ID продукта
 */
export function validateProductId(productId) {
    const id = parseInt(productId, 10);
    return !isNaN(id) && id > 0;
}

/**
 * Валидация количества
 */
export function validateQuantity(quantity) {
    const qty = parseInt(quantity, 10);
    return !isNaN(qty) && qty > 0 && qty <= DEFAULTS.MAX_QUANTITY;
}

/**
 * Валидация email
 */
export function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * Валидация телефона
 */
export function validatePhone(phone) {
    const cleaned = phone.replace(/\D/g, '');
    return cleaned.length >= 10 && cleaned.length <= 15;
}

/**
 * Санитизация строки для безопасного отображения
 */
export function sanitizeString(str) {
    if (typeof str !== 'string') return '';
    
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * Проверка, является ли значение пустым
 */
export function isEmpty(value) {
    return value === null || 
           value === undefined || 
           value === '' || 
           (Array.isArray(value) && value.length === 0) ||
           (typeof value === 'object' && Object.keys(value).length === 0);
}