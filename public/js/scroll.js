function initializePopupObserver() {
    // انتخاب المان اصلی که تغییرات آن را رصد می‌کنیم
    const popupContainer = document.querySelector('.hpo-popup-container');
    
    if (!popupContainer) {
        // اگر المان وجود نداشت، بعد از 500 میلی‌ثانیه مجددا چک می‌کنیم
        setTimeout(initializePopupObserver, 500);
        return;
    }

    // تنظیمات MutationObserver برای رصد تغییرات در فرزندان و زیردرخت
    const observerConfig = {
        childList: true,
        subtree: true
    };

    // تابعی که هنگام تغییرات اجرا می‌شود
    const mutationCallback = function(mutationsList, observer) {
        handleProductListChanges();
    };

    // ایجاد مشاهده‌گر
    const observer = new MutationObserver(mutationCallback);
    observer.observe(popupContainer, observerConfig);

    // یک بار در ابتدا نیز چک می‌کنیم
    handleProductListChanges();
}

function handleProductListChanges() {
    const productList = document.querySelector('.hpo-product-list');
    
    if (!productList) {
        return; // اگر لیست محصولات وجود ندارد خارج می‌شویم
    }

    // پیدا کردن گزینه‌های محصول (حداقل 2 مورد)
    const productOptions = document.querySelectorAll('.hpo-product-option');
    if (productOptions.length < 2) return;

    // اضافه کردن ایونت به هر گزینه (با جلوگیری از اضافه شدن تکراری)
    productOptions.forEach(option => {
        // اگر قبلا ایونت اضافه شده، اضافه نکن
        if (option.dataset.clickEnabled) return;
        
        option.addEventListener('click', function() {
            const targetSection = document.getElementById('weight-section');
            if (targetSection) {
                targetSection.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });

        // علامتگذاری المان برای جلوگیری از اضافه شدن تکراری
        option.dataset.clickEnabled = 'true';
    });
}

// شروع رصد از هنگام لود شدن صفحه
document.addEventListener('DOMContentLoaded', initializePopupObserver);