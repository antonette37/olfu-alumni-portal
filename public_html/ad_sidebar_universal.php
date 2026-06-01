<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); } 
// Get current page for active state highlighting
$current_page = isset($current_page) ? $current_page : basename($_SERVER['PHP_SELF']);
// Pages that should auto-expand Content Management dropdown
$content_management_pages = ['ad_announcements.php', 'ad_events.php', 'ad_content_management.php', 'ad_gallery.php'];
$is_content_management_active = in_array($current_page, $content_management_pages);
// Reports dropdown pages
$reports_pages = ['ad_reports.php', 'ad_archives.php', 'ad_contactmessages.php'];
$is_reports_active = in_array($current_page, $reports_pages);
?>
<style>
  /* Rail width: hover/focus-within keeps keyboard access; no overflow clip so dropdowns show */
  #sidebar.ad-universal-sidebar {
    width: 4rem;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }
  #sidebar.ad-universal-sidebar:hover,
  #sidebar.ad-universal-sidebar:focus-within {
    width: 16rem;
  }
  #sidebar.ad-universal-sidebar ~ main,
  #sidebar.ad-universal-sidebar ~ .main-content {
    transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }
  /* Main (or legacy wrappers) that follow the sidebar in the DOM */
  #sidebar.ad-universal-sidebar:hover ~ main,
  #sidebar.ad-universal-sidebar:focus-within ~ main,
  #sidebar.ad-universal-sidebar:hover ~ .main-content,
  #sidebar.ad-universal-sidebar:focus-within ~ .main-content {
    margin-left: 16rem !important;
    transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }
  @media (max-width: 1023px) {
    #sidebar.ad-universal-sidebar:hover ~ main,
    #sidebar.ad-universal-sidebar:focus-within ~ main,
    #sidebar.ad-universal-sidebar:hover ~ .main-content,
    #sidebar.ad-universal-sidebar:focus-within ~ .main-content {
      margin-left: 0 !important;
    }
  }
</style>
<aside id="sidebar" class="ad-universal-sidebar group bg-[#800000] text-white fixed top-16 bottom-0 z-40 pt-4">
    <!-- Profile Section -->
    <div class="flex flex-col items-center px-4 py-6 border-b border-[#600000]">
        <div class="flex flex-col items-center">
            <div class="relative w-12 h-12 group-hover:w-24 group-hover:h-24 transition-all duration-300">
                <div class="w-full h-full rounded-full bg-[#600000] flex items-center justify-center border-2 border-white shadow-md">
                    <i class="fas fa-user-shield text-white text-xl group-hover:text-3xl transition-all duration-300"></i>
                </div>
            </div>
            <div class="hidden group-hover:block text-center mt-3 px-1">
                <h3 class="font-semibold text-sm leading-snug">Geraldine Layugan</h3>
                <p class="text-xs text-[#ffcccc]">System Administrator</p>
            </div>
        </div>
    </div>
    
    <ul class="space-y-2 pl-2 pr-2 mt-4">
        <!-- Dashboard -->
        <li class="<?php echo ($current_page === 'ad_dashboard.php') ? 'bg-[#600000]' : 'hover:bg-[#600000]'; ?> rounded transition cursor-pointer">
            <a href="ad_dashboard.php" class="flex items-center justify-center group-hover:justify-start px-3 py-3">
                <i class="fas fa-tachometer-alt text-lg flex-shrink-0"></i>
                <div class="hidden group-hover:block ml-3 transition-opacity duration-300 opacity-0 group-hover:opacity-100">
                    <p class="font-semibold text-sm">Dashboard</p>
                    <p class="text-xs text-[#ffcccc]">Admin Overview</p>
                </div>
            </a>
        </li>

        <!-- User Management Dropdown -->
        <?php 
        $user_management_pages = ['ad_user_management.php', 'ad_alumnirecord.php', 'ad_alumnidirectory.php', 'ad_logs.php', 'ad_alumni_id_check.php', 'ad_viewprofile.php'];
        $is_user_management_active = in_array($current_page, $user_management_pages);
        ?>
        <li class="relative <?php echo $is_user_management_active ? 'bg-[#600000]' : ''; ?>">
            <button id="sbUserManagementBtn" class="flex items-center justify-center group-hover:justify-start w-full hover:bg-[#600000] px-3 py-3 rounded transition">
                <i class="fas fa-users-cog text-lg flex-shrink-0"></i>
                <div class="hidden group-hover:block ml-3 transition-opacity duration-300 opacity-0 group-hover:opacity-100">
                    <p class="font-semibold text-sm">User Management</p>
                    <p class="text-xs text-[#ffcccc]">Users, directory, logs</p>
                </div>
                <i class="fas fa-chevron-down text-xs ml-auto hidden group-hover:inline"></i>
            </button>
            <div id="sbUserManagementDropdown" class="hidden absolute left-0 mt-2 w-56 bg-white rounded-md shadow-lg py-1 z-50">
                <a href="ad_user_management.php" class="block px-4 py-2 text-sm <?php echo ($current_page === 'ad_user_management.php') ? 'bg-green-100 text-green-800 font-semibold' : 'text-gray-700 hover:bg-gray-100'; ?>">
                    <i class="fas fa-user-cog mr-2"></i>Manage Users
                </a>
                <a href="ad_alumnirecord.php" class="block px-4 py-2 text-sm <?php echo ($current_page === 'ad_alumnirecord.php') ? 'bg-green-100 text-green-800 font-semibold' : 'text-gray-700 hover:bg-gray-100'; ?>">
                    <i class="fas fa-address-book mr-2"></i>Alumni Records
                </a>
                <a href="ad_alumnidirectory.php" class="block px-4 py-2 text-sm <?php echo ($current_page === 'ad_alumnidirectory.php') ? 'bg-green-100 text-green-800 font-semibold' : 'text-gray-700 hover:bg-gray-100'; ?>">
                    <i class="fas fa-sitemap mr-2"></i>Alumni Directory
                </a>
                <a href="ad_logs.php" class="block px-4 py-2 text-sm <?php echo ($current_page === 'ad_logs.php') ? 'bg-green-100 text-green-800 font-semibold' : 'text-gray-700 hover:bg-gray-100'; ?>">
                    <i class="fas fa-history mr-2"></i>System Logs
                </a>
            </div>
        </li>

        <!-- Reports Dropdown -->
        <li class="relative <?php echo $is_reports_active ? 'bg-[#600000]' : ''; ?>">
            <button id="sbReportsBtn" class="flex items-center justify-center group-hover:justify-start w-full hover:bg-[#600000] px-3 py-3 rounded transition">
                <i class="fas fa-chart-bar text-lg flex-shrink-0"></i>
                <div class="hidden group-hover:block ml-3 transition-opacity duration-300 opacity-0 group-hover:opacity-100">
                    <p class="font-semibold text-sm">Reports</p>
                    <p class="text-xs text-[#ffcccc]">Analytics & Exports</p>
                </div>
                <i class="fas fa-chevron-down text-xs ml-auto hidden group-hover:inline"></i>
            </button>
            <div id="sbReportsDropdown" class="hidden absolute left-0 mt-2 w-56 bg-white rounded-md shadow-lg py-1 z-50">
                <a href="ad_reports.php" class="block px-4 py-2 text-sm <?php echo ($current_page === 'ad_reports.php') ? 'bg-green-100 text-green-800 font-semibold' : 'text-gray-700 hover:bg-gray-100'; ?>">
                    <i class="fas fa-chart-bar mr-2"></i>All Reports
                </a>
                <a href="ad_archives.php" class="block px-4 py-2 text-sm <?php echo ($current_page === 'ad_archives.php') ? 'bg-green-100 text-green-800 font-semibold' : 'text-gray-700 hover:bg-gray-100'; ?>">
                    <i class="fas fa-archive mr-2"></i>Archives
                </a>
                <a href="ad_contactmessages.php" class="block px-4 py-2 text-sm <?php echo ($current_page === 'ad_contactmessages.php') ? 'bg-green-100 text-green-800 font-semibold' : 'text-gray-700 hover:bg-gray-100'; ?>">
                    <i class="fas fa-envelope mr-2"></i>Support Tickets
                </a>
            </div>
        </li>

        <!-- Content Management Dropdown -->
        <li class="relative <?php echo $is_content_management_active ? 'bg-[#600000]' : ''; ?>">
            <button id="sbContentManagementBtn" class="flex items-center justify-center group-hover:justify-start w-full hover:bg-[#600000] px-3 py-3 rounded transition">
                <i class="fas fa-cogs text-lg flex-shrink-0"></i>
                <div class="hidden group-hover:block ml-3 transition-opacity duration-300 opacity-0 group-hover:opacity-100">
                    <p class="font-semibold text-sm">Content Management</p>
                    <p class="text-xs text-[#ffcccc]">Events, Announcements</p>
                </div>
                <i class="fas fa-chevron-down text-xs ml-auto hidden group-hover:inline"></i>
            </button>
            <div id="sbContentManagementDropdown" class="hidden absolute left-0 mt-2 w-56 bg-white rounded-md shadow-lg py-1 z-50">
                <a href="ad_events.php" class="block px-4 py-2 text-sm <?php echo ($current_page === 'ad_events.php') ? 'bg-green-100 text-green-800 font-semibold' : 'text-gray-700 hover:bg-gray-100'; ?>">
                    <i class="fas fa-calendar-alt mr-2"></i>Events
                </a>
                <a href="ad_announcements.php" class="block px-4 py-2 text-sm <?php echo ($current_page === 'ad_announcements.php') ? 'bg-green-100 text-green-800 font-semibold' : 'text-gray-700 hover:bg-gray-100'; ?>">
                    <i class="fas fa-bullhorn mr-2"></i>Announcements
                </a>
                <a href="ad_content_management.php" class="block px-4 py-2 text-sm <?php echo ($current_page === 'ad_content_management.php') ? 'bg-green-100 text-green-800 font-semibold' : 'text-gray-700 hover:bg-gray-100'; ?>">
                    <i class="fas fa-file-alt mr-2"></i>Content Management
                </a>
                <a href="ad_gallery.php" class="block px-4 py-2 text-sm <?php echo ($current_page === 'ad_gallery.php') ? 'bg-green-100 text-green-800 font-semibold' : 'text-gray-700 hover:bg-gray-100'; ?>">
                    <i class="fas fa-images mr-2"></i>Alumni Gallery
                </a>
            </div>
        </li>


        <!-- Logout -->
        <li class="hover:bg-[#600000] rounded transition cursor-pointer">
            <a href="ad_logout.php" class="flex items-center justify-center group-hover:justify-start px-3 py-3">
                <i class="fas fa-sign-out-alt text-lg flex-shrink-0"></i>
                <div class="hidden group-hover:block ml-3 transition-opacity duration-300 opacity-0 group-hover:opacity-100">
                    <p class="font-semibold text-sm">Logout</p>
                    <p class="text-xs text-[#ffcccc]">Sign Out</p>
                </div>
            </a>
        </li>
    </ul>

    <script>
    (function(){
        const sidebar = document.getElementById('sidebar');
        const menuA = document.getElementById('sbUserManagementDropdown');
        const menuB = document.getElementById('sbContentManagementDropdown');
        const menuC = document.getElementById('sbReportsDropdown');

        try { localStorage.removeItem('sidebarOpen'); } catch (e) {}
        document.body.classList.remove('sidebar-open');
        
        // Function to close all dropdowns
        function closeAllDropdowns() {
            if (menuA) menuA.classList.add('hidden');
            if (menuB) menuB.classList.add('hidden');
            if (menuC) menuC.classList.add('hidden');
        }
        
        // Close dropdowns when pointer leaves the sidebar (collapsed rail)
        if (sidebar) {
            sidebar.addEventListener('mouseleave', function() {
                closeAllDropdowns();
            });
            sidebar.addEventListener('focusout', function(e) {
                if (!sidebar.contains(e.relatedTarget)) {
                    closeAllDropdowns();
                }
            });
            sidebar.addEventListener('transitionend', function(e) {
                if (e.propertyName === 'width') {
                    var w = sidebar.offsetWidth || parseFloat(getComputedStyle(sidebar).width);
                    if (w <= 80) closeAllDropdowns();
                }
            });
        }
        
        // Dropdown functionality
        const btnA = document.getElementById('sbUserManagementBtn');
        if (btnA && menuA) {
            btnA.addEventListener('click', function(e){ 
                e.stopPropagation(); 
                menuA.classList.toggle('hidden');
                // Close other dropdowns when opening this one
                if (menuB) menuB.classList.add('hidden');
                if (menuC) menuC.classList.add('hidden');
            });
            // Only auto-close if not on a user management page
            <?php if (!$is_user_management_active): ?>
            document.addEventListener('click', function(e){ 
                if (!btnA.contains(e.target) && !menuA.contains(e.target)) {
                    menuA.classList.add('hidden'); 
                }
            });
            <?php endif; ?>
        }
        
        const btnB = document.getElementById('sbContentManagementBtn');
        if (btnB && menuB) {
            btnB.addEventListener('click', function(e){ 
                e.stopPropagation(); 
                menuB.classList.toggle('hidden');
                // Close other dropdowns when opening this one
                if (menuA) menuA.classList.add('hidden');
                if (menuC) menuC.classList.add('hidden');
            });
            // Only auto-close if not on a content management page
            <?php if (!$is_content_management_active): ?>
            document.addEventListener('click', function(e){ 
                if (!btnB.contains(e.target) && !menuB.contains(e.target)) {
                    menuB.classList.add('hidden'); 
                }
            });
            <?php endif; ?>
        }
        
        const btnC = document.getElementById('sbReportsBtn');
        if (btnC && menuC) {
            btnC.addEventListener('click', function(e){ 
                e.stopPropagation(); 
                menuC.classList.toggle('hidden');
                // Close other dropdowns when opening this one
                if (menuA) menuA.classList.add('hidden');
                if (menuB) menuB.classList.add('hidden');
            });
            // Only auto-close if not on a reports page
            <?php if (!$is_reports_active): ?>
            document.addEventListener('click', function(e){ 
                if (!btnC.contains(e.target) && !menuC.contains(e.target)) {
                    menuC.classList.add('hidden'); 
                }
            });
            <?php endif; ?>
        }
        
    })();
    </script>
</aside>
