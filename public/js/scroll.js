// تابع debounce برای بهینه‌سازی
function debounce(func, delay) {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => func.apply(this, args), delay);
    };
}

// ذخیره‌سازی عناصر با ایونت‌های فعال
const eventRegistry = new WeakMap();

// بررسی وجود ایونت
function isEventRegistered(element, type) {
    return eventRegistry.has(element) && eventRegistry.get(element).includes(type);
}

// ثبت ایونت
function registerEvent(element, type) {
    if (!eventRegistry.has(element)) eventRegistry.set(element, []);
    eventRegistry.get(element).push(type);
}

// اسکرول به بخش وزن
function scrollToWeightSection() {
    document.getElementById('weight-section')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });
}

// مدیریت محصولات
function handleProductListChanges() {
    document.querySelectorAll('.hpo-product-option').forEach(option => {
        if (!isEventRegistered(option, 'click')) {
            option.addEventListener('click', scrollToWeightSection);
            registerEvent(option, 'click');
        }
    });
}

// تنظیمات مشاهده‌گر
const observerConfig = {
    childList: true,
    subtree: true,
    attributes: false,
    characterData: false
};

// راه‌اندازی مشاهده‌گر
function initObserver() {
    const observer = new MutationObserver(debounce(() => {
        handleProductListChanges();
    }, 100));

    const checkContainer = () => {
        const container = document.querySelector('.hpo-popup-container');
        if (container) {
            observer.observe(container, observerConfig);
            handleProductListChanges();
        } else {
            requestAnimationFrame(checkContainer);
        }
    };
    
    checkContainer();
}

// شروع پس از آمادگی DOM
document.addEventListener('DOMContentLoaded', initObserver);