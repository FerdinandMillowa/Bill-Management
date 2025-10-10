// Enhanced Bill Management System JavaScript
class BillManagementSystem {
  constructor() {
      this.init();
  }

  init() {
      this.attachEventListeners();
      this.initDropdownMenu();
      this.initFormValidations();
      this.addAccessibilityFeatures();
  }

  // Enhanced dropdown menu functionality
  initDropdownMenu() {
      const toggleBtn = document.querySelector('.toggle_btn');
      const dropdownMenu = document.querySelector('.dropdown_menu');

      if (toggleBtn && dropdownMenu) {
          toggleBtn.addEventListener('click', (e) => {
              e.stopPropagation();
              dropdownMenu.classList.toggle('open');
              this.toggleAriaExpanded(toggleBtn);
          });

          // Close dropdown when clicking outside
          document.addEventListener('click', (e) => {
              if (!dropdownMenu.contains(e.target) && !toggleBtn.contains(e.target)) {
                  dropdownMenu.classList.remove('open');
                  this.setAriaExpanded(toggleBtn, false);
              }
          });

          // Close dropdown on escape key
          document.addEventListener('keydown', (e) => {
              if (e.key === 'Escape' && dropdownMenu.classList.contains('open')) {
                  dropdownMenu.classList.remove('open');
                  this.setAriaExpanded(toggleBtn, false);
                  toggleBtn.focus();
              }
          });
      }
  }

  // Accessibility helpers
  toggleAriaExpanded(element) {
      const isExpanded = element.getAttribute('aria-expanded') === 'true';
      this.setAriaExpanded(element, !isExpanded);
  }

  setAriaExpanded(element, expanded) {
      element.setAttribute('aria-expanded', expanded.toString());
  }

  addAccessibilityFeatures() {
      // Add aria-labels to interactive elements
      const toggleBtn = document.querySelector('.toggle_btn');
      if (toggleBtn && !toggleBtn.getAttribute('aria-label')) {
          toggleBtn.setAttribute('aria-label', 'Toggle navigation menu');
          toggleBtn.setAttribute('aria-expanded', 'false');
          toggleBtn.setAttribute('aria-haspopup', 'true');
      }

      // Add loading states to forms
      this.addFormLoadingStates();
  }

  addFormLoadingStates() {
      document.addEventListener('submit', (e) => {
          const form = e.target;
          const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
          
          if (submitBtn) {
              submitBtn.disabled = true;
              submitBtn.innerHTML = '<span class="loading-spinner"></span> Processing...';
              
              // Re-enable button if form submission fails
              setTimeout(() => {
                  if (submitBtn.disabled) {
                      submitBtn.disabled = false;
                      submitBtn.textContent = submitBtn.getAttribute('data-original-text') || 'Submit';
                  }
              }, 5000);
          }
      });
  }

  attachEventListeners() {
      document.addEventListener('DOMContentLoaded', () => {
          this.attachFormValidations();
          this.enhanceSelectElements();
          this.addFormPersistence();
      });
  }

  enhanceSelectElements() {
      // Add enhanced functionality to select elements
      const selectElements = document.querySelectorAll('select');
      selectElements.forEach(select => {
          select.addEventListener('change', (e) => {
              e.target.classList.toggle('has-value', e.target.value !== '');
          });
          
          // Initialize has-value class
          if (select.value !== '') {
              select.classList.add('has-value');
          }
      });
  }

  addFormPersistence() {
      // Save form data to localStorage on input
      const forms = document.querySelectorAll('form');
      forms.forEach(form => {
          const inputs = form.querySelectorAll('input, select, textarea');
          const formId = form.id || 'form-' + Math.random().toString(36).substr(2, 9);
          
          inputs.forEach(input => {
              const storageKey = `${formId}-${input.name}`;
              const savedValue = localStorage.getItem(storageKey);
              
              if (savedValue && !input.value) {
                  input.value = savedValue;
              }
              
              input.addEventListener('input', (e) => {
                  localStorage.setItem(storageKey, e.target.value);
              });
          });
          
          // Clear saved data on successful form submission
          form.addEventListener('submit', () => {
              inputs.forEach(input => {
                  const storageKey = `${formId}-${input.name}`;
                  localStorage.removeItem(storageKey);
              });
          });
      });
  }

  initFormValidations() {
      // Add real-time validation
      const forms = document.querySelectorAll('form');
      forms.forEach(form => {
          const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
          inputs.forEach(input => {
              input.addEventListener('blur', () => this.validateField(input));
              input.addEventListener('input', () => this.clearFieldError(input));
          });
      });
  }

  validateField(field) {
      this.clearFieldError(field);
      
      if (!field.checkValidity()) {
          this.showFieldError(field, this.getValidationMessage(field));
          return false;
      }
      
      // Custom validations
      if (field.type === 'email' && field.value) {
          if (!this.isValidEmail(field.value)) {
              this.showFieldError(field, 'Please enter a valid email address.');
              return false;
          }
      }
      
      if (field.name === 'phone' && field.value) {
          if (!this.isValidPhone(field.value)) {
              this.showFieldError(field, 'Please enter a valid phone number starting with +265 or 0 followed by 9 digits.');
              return false;
          }
      }
      
      if (field.type === 'number' && field.value) {
          if (parseFloat(field.value) <= 0) {
              this.showFieldError(field, 'Amount must be greater than zero.');
              return false;
          }
      }
      
      return true;
  }

  showFieldError(field, message) {
      field.classList.add('error');
      
      let errorElement = field.parentNode.querySelector('.field-error');
      if (!errorElement) {
          errorElement = document.createElement('div');
          errorElement.className = 'field-error';
          field.parentNode.appendChild(errorElement);
      }
      
      errorElement.textContent = message;
      errorElement.setAttribute('role', 'alert');
  }

  clearFieldError(field) {
      field.classList.remove('error');
      const errorElement = field.parentNode.querySelector('.field-error');
      if (errorElement) {
          errorElement.remove();
      }
  }

  getValidationMessage(field) {
      if (field.validity.valueMissing) {
          return 'This field is required.';
      }
      if (field.validity.typeMismatch) {
          return 'Please enter a valid value.';
      }
      if (field.validity.patternMismatch) {
          return field.title || 'Please match the requested format.';
      }
      return 'Please correct this field.';
  }

  isValidEmail(email) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(email);
  }

  isValidPhone(phone) {
      const phoneRegex = /^(?:\+265|0)\d{9}$/;
      return phoneRegex.test(phone);
  }

  // Enhanced form validation functions
  attachFormValidations() {
      // Add Bill form validation
      const addBillForm = document.querySelector('form[action*="add-bill"]');
      if (addBillForm) {
          addBillForm.addEventListener('submit', (e) => this.validateAddBillForm(e));
      }

      // Add Customer form validation
      const addCustomerForm = document.querySelector('form[action*="add-customer"]');
      if (addCustomerForm) {
          addCustomerForm.addEventListener('submit', (e) => this.validateAddCustomerForm(e));
      }

      // Add Payment form validation
      const addPaymentForm = document.querySelector('form[action*="add-payment"]');
      if (addPaymentForm) {
          addPaymentForm.addEventListener('submit', (e) => this.validateAddPaymentForm(e));
      }
  }

  validateAddBillForm(event) {
      const form = event.target;
      const billName = form.querySelector('#bill-name, input[name="bill_name"]');
      const amount = form.querySelector('#amount, input[name="amount"]');
      const customerId = form.querySelector('#customer_id, select[name="customer_id"]');
      
      let isValid = true;

      if (!this.validateField(billName)) isValid = false;
      if (!this.validateField(amount)) isValid = false;
      if (!this.validateField(customerId)) isValid = false;

      if (!isValid) {
          event.preventDefault();
          this.showToast('Please correct the errors in the form.', 'error');
          return false;
      }

      return true;
  }

  validateAddCustomerForm(event) {
      const form = event.target;
      const customerName = form.querySelector('#customer-name, input[name="first_name"]');
      const email = form.querySelector('#email, input[name="email"]');
      const phone = form.querySelector('#phone, input[name="phone"]');
      
      let isValid = true;

      if (!this.validateField(customerName)) isValid = false;
      if (!this.validateField(email)) isValid = false;
      if (!this.validateField(phone)) isValid = false;

      if (!isValid) {
          event.preventDefault();
          this.showToast('Please correct the errors in the form.', 'error');
          return false;
      }

      return true;
  }

  validateAddPaymentForm(event) {
      const form = event.target;
      const customerId = form.querySelector('#customer-id, select[name="customer_id"]');
      const amount = form.querySelector('#amount, input[name="amount"]');
      const paymentMethod = form.querySelector('#payment-method, select[name="payment_method"]');
      
      let isValid = true;

      if (!this.validateField(customerId)) isValid = false;
      if (!this.validateField(amount)) isValid = false;
      if (!this.validateField(paymentMethod)) isValid = false;

      if (!isValid) {
          event.preventDefault();
          this.showToast('Please correct the errors in the form.', 'error');
          return false;
      }

      return true;
  }

  // Utility function for showing toast messages
  showToast(message, type = 'info') {
      // Remove existing toasts
      const existingToasts = document.querySelectorAll('.toast-message');
      existingToasts.forEach(toast => toast.remove());

      const toast = document.createElement('div');
      toast.className = `toast-message toast-${type}`;
      toast.textContent = message;
      toast.setAttribute('role', 'alert');
      toast.setAttribute('aria-live', 'assertive');

      // Add styles if not already present
      if (!document.querySelector('#toast-styles')) {
          const styles = document.createElement('style');
          styles.id = 'toast-styles';
          styles.textContent = `
              .toast-message {
                  position: fixed;
                  top: 20px;
                  right: 20px;
                  padding: 12px 20px;
                  border-radius: 4px;
                  color: white;
                  font-weight: bold;
                  z-index: 10000;
                  animation: slideInRight 0.3s ease-out;
                  max-width: 300px;
              }
              .toast-success { background: #4CAF50; }
              .toast-error { background: #f44336; }
              .toast-info { background: #2196F3; }
              .toast-warning { background: #ff9800; }
              @keyframes slideInRight {
                  from { transform: translateX(100%); opacity: 0; }
                  to { transform: translateX(0); opacity: 1; }
              }
          `;
          document.head.appendChild(styles);
      }

      document.body.appendChild(toast);

      // Auto remove after 5 seconds
      setTimeout(() => {
          toast.style.animation = 'slideInRight 0.3s ease-out reverse';
          setTimeout(() => toast.remove(), 300);
      }, 5000);
  }

  // Data export functionality
  exportTableData(tableId, filename = 'data') {
      const table = document.getElementById(tableId);
      if (!table) return;

      let csv = [];
      const rows = table.querySelectorAll('tr');

      for (let i = 0; i < rows.length; i++) {
          const row = [], cols = rows[i].querySelectorAll('td, th');
          
          for (let j = 0; j < cols.length; j++) {
              let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
              data = data.replace(/"/g, '""');
              row.push('"' + data + '"');
          }
          
          csv.push(row.join(','));
      }

      const csvString = csv.join('\n');
      const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      
      if (navigator.msSaveBlob) {
          navigator.msSaveBlob(blob, filename + '.csv');
      } else {
          link.href = URL.createObjectURL(blob);
          link.download = filename + '.csv';
          link.style.visibility = 'hidden';
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
      }
  }
}

// Initialize the application
document.addEventListener('DOMContentLoaded', () => {
  window.billManagementSystem = new BillManagementSystem();
});

// Utility function for external use
function exportToCSV(tableId, filename) {
  if (window.billManagementSystem) {
      window.billManagementSystem.exportTableData(tableId, filename);
  }
}