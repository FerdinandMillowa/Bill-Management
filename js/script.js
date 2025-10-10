// Validation for Add Bill form
function validateAddBillForm(event) {
    const billName = document.getElementById('bill-name').value.trim();
    const amount = document.getElementById('amount').value.trim();
  
    if (!billName) {
      alert("Bill name is required.");
      event.preventDefault();
      return false;
    }
  
    if (!amount || isNaN(amount) || amount <= 0) {
      alert("Please enter a valid amount greater than zero.");
      event.preventDefault();
      return false;
    }
  
    return true;
  }
  
  // Validation for Add Customer form
  function validateAddCustomerForm(event) {
    const customerName = document.getElementById('customer-name').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
  
    if (!customerName) {
      alert("Customer name is required.");
      event.preventDefault();
      return false;
    }
  
    if (!email || !/\S+@\S+\.\S+/.test(email)) {
      alert("Please enter a valid email address.");
      event.preventDefault();
      return false;
    }
  
    if (!phone || isNaN(phone)) {
      alert("Please enter a valid phone number."); //Don't forget to make sure number is 10 digits
      event.preventDefault();
      return false;
    }
  
    return true;
  }
  
  // Validation for Add Payment form
  function validateAddPaymentForm(event) {
    const customerId = document.getElementById('customer-id').value.trim();
    const billId = document.getElementById('bill-id').value.trim();
    const amount = document.getElementById('amount').value.trim();
    const paymentMethod = document.getElementById('payment-method').value;
  
    if (!customerId || isNaN(customerId)) {
      alert("Please enter a valid customer ID.");
      event.preventDefault();
      return false;
    }
  
    if (!billId || isNaN(billId)) {
      alert("Please enter a valid bill ID.");
      event.preventDefault();
      return false;
    }
  
    if (!amount || isNaN(amount) || amount <= 0) {
      alert("Please enter a valid payment amount greater than zero.");
      event.preventDefault();
      return false;
    }
  
    if (!paymentMethod) {
      alert("Please select a payment method.");
      event.preventDefault();
      return false;
    }
  
    return true;
  }
  
  // Attach validation functions to forms
  document.addEventListener("DOMContentLoaded", () => {
    // Add Bill form validation
    const addBillForm = document.querySelector('form[action="add-bill.php"]');
    if (addBillForm) {
      addBillForm.addEventListener("submit", validateAddBillForm);
    }
  
    // Add Customer form validation
    const addCustomerForm = document.querySelector('form[action="add-customer.php"]');
    if (addCustomerForm) {
      addCustomerForm.addEventListener("submit", validateAddCustomerForm);
    }
  
    // Add Payment form validation
    const addPaymentForm = document.querySelector('form[action="add-payment.php"]');
    if (addPaymentForm) {
      addPaymentForm.addEventListener("submit", validateAddPaymentForm);
    }
  });

  const toggleBtn = document.querySelector('.toggle_btn');
  const dropdownMenu = document.querySelector('.dropdown_menu');

  toggleBtn.addEventListener('click', () => {
      dropdownMenu.classList.toggle('open');
  });
  