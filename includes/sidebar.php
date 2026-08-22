<!-- Sidebar -->
<?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
<div class="sidebar w-64 bg-blue-100 shadow-lg hover:ring-1 hover:ring-pink-500/20 hidden md:flex flex-col sticky top-0 self-start h-screen overflow-y-auto transition-colors duration-300">
    <div class="p-6 border-b border-gray-200">
        <div class="flex items-center space-x-3">
            <span class="text-2xl bg-pink-500/20 rounded-full w-12 h-12 flex items-center justify-center">🩸</span>
            <div>
                <h1 class="font-bold text-lg text-red-700">BloodLife</h1>
                <p class="text-sm text-gray-600">Save Lives Together</p>

            </div>
        </div>
    </div>
    <nav class="flex-1 px-4 py-6 space-y-2">
        <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg transition <?= $currentPage === 'dashboard.php' ? 'bg-red-50 text-red-700 font-semibold ring-1 ring-pink-500/30' : 'text-gray-700 hover:bg-red-50 hover:text-red-700 hover:ring-1 hover:ring-pink-500/20' ?>">
            <span>📊</span> <span>Dashboard</span>
        </a>
        <a href="donation_history_crud.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg transition <?= $currentPage === 'donation_history_crud.php' ? 'bg-red-50 text-red-700 font-semibold ring-1 ring-pink-500/30' : 'text-gray-700 hover:bg-red-50 hover:text-red-700 hover:ring-1 hover:ring-pink-500/20' ?>">
            <span>⚡</span> <span>Donation History</span>
        </a>
        <a href="users_crud.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg transition <?= $currentPage === 'users_crud.php' ? 'bg-red-50 text-red-700 font-semibold ring-1 ring-pink-500/30' : 'text-gray-700 hover:bg-red-50 hover:text-red-700 hover:ring-1 hover:ring-pink-500/20' ?>">
            <span>👥</span> <span>Users</span>
        </a>
        <a href="assignments.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg transition <?= $currentPage === 'assignments.php' ? 'bg-red-50 text-red-700 font-semibold ring-1 ring-pink-500/30' : 'text-gray-700 hover:bg-red-50 hover:text-red-700 hover:ring-1 hover:ring-pink-500/20' ?>">
            <span>🎬</span> <span>Assigned Donors</span>
        </a>
        <a href="donor_crud.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg transition <?= $currentPage === 'donor_crud.php' ? 'bg-red-50 text-red-700 font-semibold ring-1 ring-pink-500/30' : 'text-gray-700 hover:bg-red-50 hover:text-red-700 hover:ring-1 hover:ring-pink-500/20' ?>">
            <span>🩸</span> <span>Donors</span>
        </a>

        <a href="blood_requests_crud.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg transition <?= $currentPage === 'blood_requests_crud.php' ? 'bg-red-50 text-red-700 font-semibold ring-1 ring-pink-500/30' : 'text-gray-700 hover:bg-red-50 hover:text-red-700 hover:ring-1 hover:ring-pink-500/20' ?>">
            <span>📋</span> <span>Blood Requests</span>
        </a>
        <a href="notifications.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg transition <?= $currentPage === 'notifications.php' ? 'bg-red-50 text-red-700 font-semibold ring-1 ring-pink-500/30' : 'text-gray-700 hover:bg-red-50 hover:text-red-700 hover:ring-1 hover:ring-pink-500/20' ?>">
            <span>🔔</span> <span>Notifications</span>
        </a>


    </nav>
    <div class="p-4 border-t border-gray-200">
        <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="w-full bg-red-600 text-white flex justify-center py-2 rounded-lg font-semibold hover:bg-red-700 transition" data-i18n="logout">Logout</a>
    </div>
</div>