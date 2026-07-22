<!-- Sidebar -->
    <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
    <div class="sidebar w-64 bg-white shadow-lg hidden md:flex flex-col sticky top-0 self-start h-screen overflow-y-auto">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center space-x-3">
                <span class="text-3xl">🩸</span>
                <div>
                    <h1 class="font-bold text-lg text-red-700">BloodLife</h1>
                    <p class="text-xs text-gray-500">CRUD Panel</p>
                </div>
            </div>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2">
            <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg transition <?= $currentPage === 'dashboard.php' ? 'bg-red-50 text-red-700 font-semibold' : 'text-gray-700 hover:bg-red-50 hover:text-red-700' ?>">
                <span>📊</span> <span data-i18n="overview">Overview</span>
            </a>
            <a href="users_crud.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg transition <?= $currentPage === 'users_crud.php' ? 'bg-red-50 text-red-700 font-semibold' : 'text-gray-700 hover:bg-red-50 hover:text-red-700' ?>">
                <span>👥</span> <span>Users</span>
            </a>
            <a href="donor_crud.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg transition <?= $currentPage === 'donor_crud.php' ? 'bg-red-50 text-red-700 font-semibold' : 'text-gray-700 hover:bg-red-50 hover:text-red-700' ?>">
                <span>🩸</span> <span>Donors</span>
            </a>
            <a href="donation_history_crud.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg transition <?= $currentPage === 'donation_history_crud.php' ? 'bg-red-50 text-red-700 font-semibold' : 'text-gray-700 hover:bg-red-50 hover:text-red-700' ?>">
                <span>⚡</span> <span>Donation History</span>
            </a>
            <a href="blood_requests_crud.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg transition <?= $currentPage === 'blood_requests_crud.php' ? 'bg-red-50 text-red-700 font-semibold' : 'text-gray-700 hover:bg-red-50 hover:text-red-700' ?>">
                <span>📋</span> <span>Blood Requests</span>
            </a>
        </nav>
        <div class="p-4 border-t border-gray-200">
            <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="w-full bg-red-600 text-white flex justify-center py-2 rounded-lg font-semibold hover:bg-red-700 transition" data-i18n="logout">Logout</a>
        </div>
    </div>
