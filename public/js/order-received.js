document.addEventListener("DOMContentLoaded", function() {
  // پیدا کردن تمامی المان‌ها با کلاس woocommerce-table__product-name product-name
  const productNames = document.querySelectorAll('.woocommerce-table__product-name.product-name');

  productNames.forEach(function(productName) {
    // بررسی اینکه آیا المان فرزند با کلاس hpo-order-item-options وجود دارد یا نه
    const optionsElement = productName.querySelector('.hpo-order-item-options');
    
    if (optionsElement) {
      // پیدا کردن تمامی المان‌های strong که شامل تگ لینک هستند
      const strongElements = productName.querySelectorAll('strong a');
      
      strongElements.forEach(function(strongElement) {
        // پاک کردن این تگ‌ها
        strongElement.parentElement.remove();
      });
    }
  });
}); 