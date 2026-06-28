document.addEventListener("DOMContentLoaded", function() {

  function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
    var overlay = document.querySelector('.sidebar-overlay');
    if (overlay) overlay.classList.toggle('active');
}
// ওভারলে ক্লিক করলে সাইডবার বন্ধ
document.addEventListener('click', function(e) {
    var sidebar = document.getElementById('sidebar');
    if (sidebar.classList.contains('show') && !sidebar.contains(e.target) && !e.target.closest('.btn-toggle-sidebar')) {
        sidebar.classList.remove('show');
        document.querySelector('.sidebar-overlay')?.classList.remove('active');
    }
});
  
    // ---- 1. Dark Theme Persistence Logic ----
    const themeToggle = document.getElementById("darkModeToggle");
    const htmlElement = document.documentElement;

    if (themeToggle) {
        const activeTheme = localStorage.getItem("system-ui-theme") || "light";
        htmlElement.setAttribute("data-bs-theme", activeTheme);
        themeToggle.checked = (activeTheme === "dark");

        themeToggle.addEventListener("change", function() {
            const selectedTheme = themeToggle.checked ? "dark" : "light";
            htmlElement.setAttribute("data-bs-theme", selectedTheme);
            localStorage.setItem("system-ui-theme", selectedTheme);
        });
    }

    // ---- 2. Offline Diagnostics Tracker ----
    const networkDot = document.getElementById("network-dot");
    const networkText = document.getElementById("network-text");

    function updateNetworkStatus() {
        if (navigator.onLine) {
            if (networkDot) {
                networkDot.className = "bi bi-circle-fill text-success";
                networkText.textContent = "Online";
            }
        } else {
            if (networkDot) {
                networkDot.className = "bi bi-circle-fill text-danger";
                networkText.textContent = "Offline Mode";
            }
        }
    }

    window.addEventListener("online", updateNetworkStatus);
    window.addEventListener("offline", updateNetworkStatus);
    updateNetworkStatus();

    // ---- 3. Service Worker registration placeholder for progressive web app support ----
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js').catch(err => {
                console.log("Service Worker compilation omitted in development: ", err);
            });
        });
    }
});

