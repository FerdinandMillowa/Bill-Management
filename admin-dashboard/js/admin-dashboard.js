/* ===============================================
ADMIN DASHBOARD JAVASCRIPT
=============================================== */

// ============= MOBILE MENU TOGGLE =============
const sideMenu = document.querySelector('aside');
const menuBtn = document.getElementById('menu-btn');
const closeBtn = document.getElementById('close-btn');

if (menuBtn) {
 menuBtn.addEventListener('click', () => {
     sideMenu.style.display = 'block';
 });
}

if (closeBtn) {
 closeBtn.addEventListener('click', () => {
     sideMenu.style.display = 'none';
 });
}

// ============= DARK MODE TOGGLE =============
const darkMode = document.querySelector('.dark-mode');

if (darkMode) {
 // Check if user has a preference saved
 const savedTheme = localStorage.getItem('dashboard-theme');
 if (savedTheme === 'dark') {
     document.body.classList.add('dark-mode-variables');
     darkMode.querySelector('span:nth-child(1)').classList.remove('active');
     darkMode.querySelector('span:nth-child(2)').classList.add('active');
 }

 darkMode.addEventListener('click', () => {
     document.body.classList.toggle('dark-mode-variables');
     darkMode.querySelector('span:nth-child(1)').classList.toggle('active');
     darkMode.querySelector('span:nth-child(2)').classList.toggle('active');
     
     // Save preference
     if (document.body.classList.contains('dark-mode-variables')) {
         localStorage.setItem('dashboard-theme', 'dark');
     } else {
         localStorage.setItem('dashboard-theme', 'light');
     }
 });
}

// ============= TAB SWITCHING =============
function switchTab(tabName) {
 // Hide all tab contents
 const tabContents = document.querySelectorAll('.tab-content');
 tabContents.forEach(content => {
     content.classList.remove('active');
 });
 
 // Remove active class from all tab buttons
 const tabBtns = document.querySelectorAll('.tab-btn');
 tabBtns.forEach(btn => {
     btn.classList.remove('active');
 });
 
 // Show selected tab content
 const selectedTab = document.getElementById(`${tabName}-tab`);
 if (selectedTab) {
     selectedTab.classList.add('active');
 }
 
 // Add active class to clicked button
 event.target.closest('.tab-btn').classList.add('active');
}

// ============= REAL-TIME CLOCK (Optional) =============
function updateClock() {
 const now = new Date();
 const options = { 
     weekday: 'long', 
     year: 'numeric', 
     month: 'long', 
     day: 'numeric',
     hour: '2-digit',
     minute: '2-digit'
 };
 const timeString = now.toLocaleDateString('en-US', options);
 
 const clockElement = document.getElementById('dashboard-clock');
 if (clockElement) {
     clockElement.textContent = timeString;
 }
}

// Update clock every minute
setInterval(updateClock, 60000);
updateClock(); // Initial call

// ============= AUTO-REFRESH DATA (Optional) =============
let autoRefreshInterval;

function startAutoRefresh(intervalMinutes = 5) {
 autoRefreshInterval = setInterval(() => {
     // Reload dashboard data via AJAX
     refreshDashboardData();
 }, intervalMinutes * 60 * 1000);
}

function stopAutoRefresh() {
 if (autoRefreshInterval) {
     clearInterval(autoRefreshInterval);
 }
}

function refreshDashboardData() {
 // This would fetch updated data via AJAX
 // For now, we'll just log
 console.log('Dashboard data refreshed at:', new Date().toLocaleTimeString());
 
 // Example AJAX call (uncomment when backend is ready):
 /*
 fetch('dashboard-data.php')
     .then(response => response.json())
     .then(data => {
         // Update statistics on page
         updateStatistics(data);
     })
     .catch(error => console.error('Error refreshing data:', error));
 */
}

// ============= NOTIFICATION/ALERT SYSTEM =============
function showNotification(message, type = 'info') {
 const notification = document.createElement('div');
 notification.className = `notification notification-${type}`;
 notification.style.cssText = `
     position: fixed;
     top: 20px;
     right: 20px;
     padding: 1rem 1.5rem;
     background: ${type === 'success' ? 'var(--color-success)' : type === 'error' ? 'var(--color-danger)' : 'var(--color-primary)'};
     color: white;
     border-radius: var(--border-radius-2);
     box-shadow: var(--box-shadow);
     z-index: 9999;
     animation: slideInRight 0.3s ease;
 `;
 notification.textContent = message;
 
 document.body.appendChild(notification);
 
 // Auto remove after 5 seconds
 setTimeout(() => {
     notification.style.animation = 'slideOutRight 0.3s ease';
     setTimeout(() => notification.remove(), 300);
 }, 5000);
}

// Add animation styles
const style = document.createElement('style');
style.textContent = `
 @keyframes slideInRight {
     from {
         transform: translateX(100%);
         opacity: 0;
     }
     to {
         transform: translateX(0);
         opacity: 1;
     }
 }
 @keyframes slideOutRight {
     from {
         transform: translateX(0);
         opacity: 1;
     }
     to {
         transform: translateX(100%);
         opacity: 0;
     }
 }
`;
document.head.appendChild(style);

// ============= CHART REFRESH FUNCTIONS =============
function updateChartData(chartInstance, newLabels, newData) {
 chartInstance.data.labels = newLabels;
 chartInstance.data.datasets[0].data = newData;
 chartInstance.update();
}

// ============= EXPORT DATA FUNCTIONS =============
function exportTableToCSV(tableId, filename = 'data.csv') {
 const table = document.querySelector(`#${tableId} table`);
 if (!table) return;
 
 let csv = [];
 const rows = table.querySelectorAll('tr');
 
 for (let i = 0; i < rows.length; i++) {
     const row = [];
     const cols = rows[i].querySelectorAll('td, th');
     
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
     navigator.msSaveBlob(blob, filename);
 } else {
     link.href = URL.createObjectURL(blob);
     link.download = filename;
     link.style.visibility = 'hidden';
     document.body.appendChild(link);
     link.click();
     document.body.removeChild(link);
 }
}

// ============= SEARCH/FILTER FUNCTIONS =============
function filterTable(inputId, tableId) {
 const input = document.getElementById(inputId);
 const filter = input.value.toUpperCase();
 const table = document.getElementById(tableId);
 const tr = table.getElementsByTagName('tr');
 
 for (let i = 1; i < tr.length; i++) {
     let found = false;
     const td = tr[i].getElementsByTagName('td');
     
     for (let j = 0; j < td.length; j++) {
         if (td[j]) {
             const txtValue = td[j].textContent || td[j].innerText;
             if (txtValue.toUpperCase().indexOf(filter) > -1) {
                 found = true;
                 break;
             }
         }
     }
     
     tr[i].style.display = found ? '' : 'none';
 }
}

// ============= CONFIRMATION DIALOGS =============
function confirmAction(message, callback) {
 if (confirm(message)) {
     callback();
 }
}

// ============= LOADING INDICATOR =============
function showLoading() {
 const loader = document.createElement('div');
 loader.id = 'dashboard-loader';
 loader.style.cssText = `
     position: fixed;
     top: 0;
     left: 0;
     width: 100%;
     height: 100%;
     background: rgba(0, 0, 0, 0.5);
     display: flex;
     align-items: center;
     justify-content: center;
     z-index: 99999;
 `;
 loader.innerHTML = `
     <div style="
         background: white;
         padding: 2rem;
         border-radius: 1rem;
         box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
     ">
         <div class="spinner"></div>
         <p style="margin-top: 1rem; text-align: center;">Loading...</p>
     </div>
 `;
 document.body.appendChild(loader);
}

function hideLoading() {
 const loader = document.getElementById('dashboard-loader');
 if (loader) {
     loader.remove();
 }
}

// ============= UTILITY FUNCTIONS =============
function formatCurrency(amount) {
 return 'MWK ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function formatDate(dateString) {
 const date = new Date(dateString);
 const options = { year: 'numeric', month: 'short', day: 'numeric' };
 return date.toLocaleDateString('en-US', options);
}

function timeAgo(dateString) {
 const date = new Date(dateString);
 const now = new Date();
 const seconds = Math.floor((now - date) / 1000);
 
 const intervals = {
     year: 31536000,
     month: 2592000,
     week: 604800,
     day: 86400,
     hour: 3600,
     minute: 60,
     second: 1
 };
 
 for (const [unit, secondsInUnit] of Object.entries(intervals)) {
     const interval = Math.floor(seconds / secondsInUnit);
     if (interval >= 1) {
         return `${interval} ${unit}${interval > 1 ? 's' : ''} ago`;
     }
 }
 
 return 'just now';
}

// ============= KEYBOARD SHORTCUTS =============
document.addEventListener('keydown', function(e) {
 // Ctrl/Cmd + K: Quick search (if implemented)
 if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
     e.preventDefault();
     const searchInput = document.getElementById('quick-search');
     if (searchInput) {
         searchInput.focus();
     }
 }
 
 // Esc: Close modals/sidebar on mobile
 if (e.key === 'Escape') {
     if (window.innerWidth <= 768 && sideMenu.style.display === 'block') {
         sideMenu.style.display = 'none';
     }
 }
});

// ============= INITIALIZE TOOLTIPS (Optional) =============
function initTooltips() {
 const tooltipElements = document.querySelectorAll('[data-tooltip]');
 tooltipElements.forEach(element => {
     element.addEventListener('mouseenter', function() {
         const tooltip = document.createElement('div');
         tooltip.className = 'tooltip';
         tooltip.textContent = this.getAttribute('data-tooltip');
         tooltip.style.cssText = `
             position: absolute;
             background: rgba(0, 0, 0, 0.8);
             color: white;
             padding: 0.5rem 1rem;
             border-radius: 0.5rem;
             font-size: 0.875rem;
             white-space: nowrap;
             z-index: 9999;
             pointer-events: none;
         `;
         document.body.appendChild(tooltip);
         
         const rect = this.getBoundingClientRect();
         tooltip.style.top = `${rect.top - tooltip.offsetHeight - 5}px`;
         tooltip.style.left = `${rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)}px`;
         
         this._tooltip = tooltip;
     });
     
     element.addEventListener('mouseleave', function() {
         if (this._tooltip) {
             this._tooltip.remove();
             this._tooltip = null;
         }
     });
 });
}

// ============= PAGE VISIBILITY API =============
// Pause auto-refresh when tab is not visible
document.addEventListener('visibilitychange', function() {
 if (document.hidden) {
     stopAutoRefresh();
 } else {
     // Optionally restart auto-refresh when tab becomes visible
     // startAutoRefresh(5);
 }
});

// ============= PERFORMANCE MONITORING =============
function logPerformance() {
 if (window.performance && window.performance.timing) {
     const perfData = window.performance.timing;
     const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
     console.log(`Dashboard loaded in ${pageLoadTime}ms`);
 }
}

// ============= ERROR HANDLING =============
window.addEventListener('error', function(e) {
 console.error('Dashboard error:', e.error);
 // Optionally send error to server for logging
});

// ============= INITIALIZE ON PAGE LOAD =============
document.addEventListener('DOMContentLoaded', function() {
 console.log('Admin Dashboard initialized');
 
 // Initialize tooltips if present
 initTooltips();
 
 // Log performance
 window.addEventListener('load', logPerformance);
 
 // Optional: Start auto-refresh (uncomment if needed)
 // startAutoRefresh(5); // Refresh every 5 minutes
});

// ============= EXPORT FUNCTIONS FOR GLOBAL USE =============
window.dashboardUtils = {
 showNotification,
 exportTableToCSV,
 filterTable,
 confirmAction,
 showLoading,
 hideLoading,
 formatCurrency,
 formatDate,
 timeAgo,
 refreshDashboardData,
 startAutoRefresh,
 stopAutoRefresh
};