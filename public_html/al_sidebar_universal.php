<?php if (session_status() === PHP_SESSION_NONE) { session_start(); } ?>
<aside id="sidebar" class="bg-green-700 text-white w-16 hover:w-64 transition-all duration-300 fixed top-16 h-full z-40 overflow-hidden pt-4 group">
    <ul class="space-y-4 pl-4 pr-4 mt-2">
        <li class="flex items-center space-x-4 hover:bg-green-600 px-2 py-2 rounded transition cursor-pointer">
            <a href="#" class="flex items-center space-x-4 w-full" onclick="event.preventDefault();">
                <i class="fas fa-bars text-2xl"></i>
            </a>
        </li>

        <li class="flex items-center space-x-4 hover:bg-green-600 px-2 py-2 rounded transition cursor-pointer">
            <a href="al_dashboard.php" class="flex items-center space-x-4 w-full">
                <i class="fas fa-home"></i>
                <div class="hidden group-hover:block transition-opacity duration-300 opacity-0 group-hover:opacity-100">
                    <p class="font-semibold">Dashboard</p>
                    <p class="text-sm text-green-200">Overview</p>
                </div>
            </a>
        </li>

        <!-- Find Alumni Dropdown (click to open) -->
        <li class="relative">
            <button id="sbFindAlumniBtn" class="flex items-center space-x-4 w-full hover:bg-green-600 px-2 py-2 rounded transition">
                <i class="fas fa-address-book"></i>
                <div class="hidden group-hover:block transition-opacity duration-300 opacity-0 group-hover:opacity-100">
                    <p class="font-semibold">Find Alumni</p>
                    <p class="text-sm text-green-200">Directory, Gallery, Card</p>
                </div>
                <i class="fas fa-chevron-down ml-auto hidden group-hover:inline"></i>
            </button>
            <div id="sbFindAlumniDropdown" class="hidden absolute left-0 mt-2 w-56 bg-white rounded-md shadow-lg py-1 z-50">
                <a href="al_directory.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Alumni Directory</a>
                <a href="al_gallery.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Alumni Gallery</a>
                <a href="alumni_card_details.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Alumni Card</a>
            </div>
        </li>

        <li class="flex items-center space-x-4 hover:bg-green-600 px-2 py-2 rounded transition cursor-pointer">
            <a href="al_events.php" class="flex items-center space-x-4 w-full">
                <i class="fas fa-calendar"></i>
                <div class="hidden group-hover:block transition-opacity duration-300 opacity-0 group-hover:opacity-100">
                    <p class="font-semibold">Events</p>
                    <p class="text-sm text-green-200">Upcoming alumni events</p>
                </div>
            </a>
        </li>

        <li class="flex items-center space-x-4 hover:bg-green-600 px-2 py-2 rounded transition cursor-pointer">
            <a href="al_career.php" class="flex items-center space-x-4 w-full">
                <i class="fas fa-briefcase"></i>
                <div class="hidden group-hover:block transition-opacity duration-300 opacity-0 group-hover:opacity-100">
                    <p class="font-semibold">Careers</p>
                    <p class="text-sm text-green-200">Find job opportunities</p>
                </div>
            </a>
        </li>

        <!-- About Dropdown (click to open) -->
        <li class="relative">
            <button id="sbAboutBtn" class="flex items-center space-x-4 w-full hover:bg-green-600 px-2 py-2 rounded transition">
                <i class="fas fa-info-circle"></i>
                <div class="hidden group-hover:block transition-opacity duration-300 opacity-0 group-hover:opacity-100">
                    <p class="font-semibold">About</p>
                    <p class="text-sm text-green-200">About, FAQs, Contact</p>
                </div>
                <i class="fas fa-chevron-down ml-auto hidden group-hover:inline"></i>
            </button>
            <div id="sbAboutDropdown" class="hidden absolute left-0 mt-2 w-56 bg-white rounded-md shadow-lg py-1 z-50">
                <a href="al_about.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">About</a>
                <a href="gen_faqs.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">FAQs</a>
                <a href="al_contact.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Contact Us</a>
                <a href="al_my_tickets.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Support History</a>
            </div>
        </li>
    </ul>

    <script>
    (function(){
        const btnA = document.getElementById('sbFindAlumniBtn');
        const menuA = document.getElementById('sbFindAlumniDropdown');
        if (btnA && menuA) {
            btnA.addEventListener('click', function(e){ e.stopPropagation(); menuA.classList.toggle('hidden'); });
            document.addEventListener('click', function(e){ if (!btnA.contains(e.target) && !menuA.contains(e.target)) menuA.classList.add('hidden'); });
        }
        const btnB = document.getElementById('sbAboutBtn');
        const menuB = document.getElementById('sbAboutDropdown');
        if (btnB && menuB) {
            btnB.addEventListener('click', function(e){ e.stopPropagation(); menuB.classList.toggle('hidden'); });
            document.addEventListener('click', function(e){ if (!btnB.contains(e.target) && !menuB.contains(e.target)) menuB.classList.add('hidden'); });
        }
    })();
    </script>
</aside>


