<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Admin Panel</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <!-- FullCalendar JS -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <style>
        body {
            @apply bg-gray-100 text-gray-800 font-sans;
        }
        #sidebar .active > a {
            @apply bg-green-800 border-r-4 border-green-400;
        }
        #sidebar .active > a .text-sm {
            @apply text-green-100;
        }
        .fc-event {
            @apply cursor-pointer;
        }
        .fc-event-title {
            @apply font-medium;
        }
        .fc-daygrid-event {
            @apply rounded-md;
        }
    </style>
</head>
<body>
    <?php include 'ad_header_universal.php'; ?>
    <?php include 'ad_sidebar_universal.php'; ?>

    <!-- Main Content -->
    <div class="main-content pt-24 ml-16 p-4 max-w-full overflow-x-auto">
        <div class="max-w-7xl mx-auto">
            <!-- Page Header -->
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Event Calendar</h2>
                <div class="flex space-x-4">
                    <button class="bg-green-700 text-white px-4 py-2 rounded-md hover:bg-green-800 transition-colors" onclick="openAddEventModal()">
                        <i class="fas fa-plus mr-2"></i>Add New Event
                    </button>
                    <button class="bg-white text-gray-700 px-4 py-2 rounded-md border border-gray-300 hover:bg-gray-50 transition-colors">
                        <i class="fas fa-download mr-2"></i>Export Calendar
                    </button>
                </div>
            </div>

            <!-- Calendar Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="relative">
                        <input type="text" placeholder="Search events..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                    <select class="border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">Event Type</option>
                        <option value="meeting">Meeting</option>
                        <option value="seminar">Seminar</option>
                        <option value="workshop">Workshop</option>
                        <option value="reunion">Reunion</option>
                    </select>
                    <select class="border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">Status</option>
                        <option value="upcoming">Upcoming</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                    </select>
                    <select class="border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">View</option>
                        <option value="month">Month</option>
                        <option value="week">Week</option>
                        <option value="day">Day</option>
                        <option value="list">List</option>
                    </select>
                </div>
            </div>

            <!-- Calendar -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div id="calendar"></div>
            </div>
        </div>
    </div>

    <!-- Add Event Modal -->
    <div id="addEventModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Event</h3>
                <form>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="eventTitle">
                            Event Title
                        </label>
                        <input type="text" id="eventTitle" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="eventType">
                            Event Type
                        </label>
                        <select id="eventType" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <option value="meeting">Meeting</option>
                            <option value="seminar">Seminar</option>
                            <option value="workshop">Workshop</option>
                            <option value="reunion">Reunion</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="eventDate">
                            Date
                        </label>
                        <input type="date" id="eventDate" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="eventTime">
                            Time
                        </label>
                        <input type="time" id="eventTime" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="eventDescription">
                            Description
                        </label>
                        <textarea id="eventDescription" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" rows="3"></textarea>
                    </div>
                    <div class="flex justify-end space-x-4">
                        <button type="button" onclick="closeAddEventModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="bg-green-700 text-white px-4 py-2 rounded-md hover:bg-green-800 transition-colors">
                            Add Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                events: [
                    {
                        title: 'Alumni Reunion',
                        start: '2024-03-15',
                        color: '#059669'
                    },
                    {
                        title: 'Career Fair',
                        start: '2024-03-20',
                        color: '#2563EB'
                    },
                    {
                        title: 'Workshop',
                        start: '2024-03-25',
                        color: '#7C3AED'
                    }
                ],
                eventClick: function(info) {
                    alert('Event: ' + info.event.title);
                }
            });
            calendar.render();
        });

        function openAddEventModal() {
            document.getElementById('addEventModal').classList.remove('hidden');
        }

        function closeAddEventModal() {
            document.getElementById('addEventModal').classList.add('hidden');
        }

        function toggleDropdown(id) {
            const dropdown = document.getElementById(id);
            dropdown.classList.toggle('hidden');
        }
    </script>
</body>
</html>
