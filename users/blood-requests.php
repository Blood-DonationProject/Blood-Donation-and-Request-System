<?php

$donors = [
    [
        'id' => '#VL-8821',
        'initials' => 'JH',
        'name' => 'Julianne Hatcher',
        'email' => 'julianne.h@email.com',
        'blood' => 'O-',
        'last_donation' => 'Oct 12, 2023',
        'status' => 'Eligible'
    ],
    [
        'id' => '#VL-9045',
        'initials' => 'MR',
        'name' => 'Marcus Reed',
        'email' => 'm.reed@clinic.org',
        'blood' => 'A+',
        'last_donation' => 'Jan 04, 2024',
        'status' => 'Requested'
    ],
    [
        'id' => '#VL-7732',
        'initials' => 'SK',
        'name' => 'Sonia Khan',
        'email' => 'sonia.khan@provider.net',
        'blood' => 'B-',
        'last_donation' => 'Nov 22, 2023',
        'status' => 'Critical'
    ],
    [
        'id' => '#VL-1102',
        'initials' => 'AL',
        'name' => 'Arthur Lawson',
        'email' => 'alawson@gmail.com',
        'blood' => 'AB+',
        'last_donation' => 'Sep 15, 2023',
        'status' => 'Ineligible'
    ],
    [
        'id' => '#VL-3498',
        'initials' => 'EL',
        'name' => 'Elena Lopez',
        'email' => 'elopez.donates@email.com',
        'blood' => 'O+',
        'last_donation' => 'Dec 30, 2023',
        'status' => 'Eligible'
    ]
];

function statusBadge($status)
{
    return match ($status) {
        'Eligible' => 'bg-green-100 text-green-700',
        'Requested' => 'bg-blue-100 text-blue-700',
        'Critical' => 'bg-red-100 text-red-700',
        default => 'bg-gray-100 text-gray-700'
    };
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>Vital Ledger Dashboard</title>

<script src="https://cdn.tailwindcss.com"></script>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

<style>

body{
    font-family: Inter,sans-serif;
}

.sidebar-item{
    @apply flex items-center gap-3 px-5 py-3 rounded-lg;
}

</style>

</head>

<body class="bg-[#faf9f8]">

<div class="flex min-h-screen">

    
    <!-- CONTENT -->
    <div class="flex-1">

        <!-- TOPBAR -->
        <header class="bg-white border-b">

            <div class="px-8 h-20 flex items-center justify-between">

                <div class="relative w-72">

                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-3 text-gray-400"></i>

                    <input
                        id="searchInput"
                        type="text"
                        placeholder="Search donor by ID or name..."
                        class="w-full pl-11 pr-4 py-3 bg-gray-50 border rounded-lg outline-none focus:ring-2 focus:ring-red-300">

                </div>

                <div class="flex items-center gap-8">

                    <i class="fa-regular fa-bell text-gray-600"></i>
                    <i class="fa-solid fa-gear text-gray-600"></i>

                    <div class="flex items-center gap-3">

                        <img
                            src="https://i.pravatar.cc/40?img=12"
                            class="w-10 h-10 rounded-full">

                        <span class="font-semibold text-sm">
                            Admin Sarah
                        </span>

                    </div>

                </div>

            </div>

        </header>

        <!-- MAIN -->
        <main class="p-8">

            <!-- TITLE -->
            <div class="flex justify-between items-start">

                <div>
                    <h1 class="text-5xl font-semibold text-red-950">
                        Donor Directory
                    </h1>

                    <p class="text-gray-500 mt-3">
                        Manage and monitor the hospital's verified blood donor network.
                    </p>
                </div>

                <button class="bg-red-900 text-white px-6 py-3 rounded-md hover:bg-red-950">
                    <i class="fa-solid fa-user-plus mr-2"></i>
                    REGISTER NEW DONOR
                </button>

            </div>

            <!-- CARDS -->
            <div class="grid lg:grid-cols-4 gap-5 mt-10">

                <div class="bg-white border rounded-xl p-6">
                    <p class="uppercase text-sm text-gray-500">
                        Total Donors
                    </p>
                    <h3 class="text-4xl font-bold mt-2">1,248</h3>
                </div>

                <div class="bg-white border rounded-xl p-6">
                    <p class="uppercase text-sm text-gray-500">
                        Eligible Now
                    </p>
                    <h3 class="text-4xl font-bold mt-2 text-red-900">842</h3>
                </div>

                <div class="bg-white border rounded-xl p-6">
                    <p class="uppercase text-sm text-gray-500">
                        Rare Types (O-)
                    </p>
                    <h3 class="text-4xl font-bold mt-2 text-red-700">54</h3>
                </div>

                <div class="bg-white border rounded-xl p-6">
                    <p class="uppercase text-sm text-gray-500">
                        Donations (MTD)
                    </p>
                    <h3 class="text-4xl font-bold mt-2">112</h3>
                </div>

            </div>

            <!-- FILTERS -->
            <div class="bg-white rounded-xl border p-5 mt-8">

                <div class="flex flex-wrap gap-4">

                    <select class="border rounded-lg px-4 py-2">
                        <option>All Blood Types</option>
                    </select>

                    <select class="border rounded-lg px-4 py-2">
                        <option>All Eligibility</option>
                    </select>

                    <select class="border rounded-lg px-4 py-2">
                        <option>Last Donation (Any)</option>
                    </select>

                </div>

            </div>

            <!-- TABLE -->
            <div class="bg-white border rounded-xl overflow-hidden mt-8">

                <table class="w-full">

                    <thead class="bg-gray-50 text-gray-500 text-sm uppercase">

                        <tr>

                            <th class="text-left p-5">Donor ID</th>
                            <th class="text-left p-5">Full Name</th>
                            <th class="text-left p-5">Blood Type</th>
                            <th class="text-left p-5">Last Donation</th>
                            <th class="text-left p-5">Status</th>
                            <th class="text-left p-5">Actions</th>

                        </tr>

                    </thead>

                    <tbody id="donorTable">

                    <?php foreach($donors as $donor): ?>

                    <tr class="border-t donorRow">

                        <td class="p-5 donorId">
                            <?= $donor['id']; ?>
                        </td>

                        <td class="p-5 donorName">

                            <div class="flex items-center gap-3">

                                <div class="w-9 h-9 rounded-full bg-red-100 text-red-700 flex items-center justify-center text-xs font-bold">
                                    <?= $donor['initials']; ?>
                                </div>

                                <div>
                                    <div class="font-semibold">
                                        <?= $donor['name']; ?>
                                    </div>

                                    <div class="text-xs text-gray-500">
                                        <?= $donor['email']; ?>
                                    </div>
                                </div>

                            </div>

                        </td>

                        <td class="p-5">

                            <span class="bg-red-800 text-white px-3 py-1 rounded-full text-sm font-semibold">
                                <?= $donor['blood']; ?>
                            </span>

                        </td>

                        <td class="p-5">
                            <?= $donor['last_donation']; ?>
                        </td>

                        <td class="p-5">

                            <span class="px-3 py-1 rounded text-xs font-medium <?= statusBadge($donor['status']); ?>">
                                <?= strtoupper($donor['status']); ?>
                            </span>

                        </td>

                        <td class="p-5">
                            <a href="#" class="text-red-800 hover:underline">
                                View Profile
                            </a>
                        </td>

                    </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

                <!-- PAGINATION -->

                <div class="border-t p-5 flex justify-between items-center">

                    <span class="text-sm text-gray-500">
                        Showing 1 to 5 of 1,248 donors
                    </span>

                    <div class="flex gap-2">

                        <button class="w-8 h-8 bg-red-900 text-white rounded">
                            1
                        </button>

                        <button class="w-8 h-8">2</button>
                        <button class="w-8 h-8">3</button>
                        <button>...</button>
                        <button>250</button>

                    </div>

                </div>

            </div>

        </main>

    </div>

</div>

<!-- SEARCH -->

<script>

const searchInput = document.getElementById('searchInput');
const rows = document.querySelectorAll('.donorRow');

searchInput.addEventListener('keyup', function(){

    const keyword = this.value.toLowerCase();

    rows.forEach(row => {

        const name = row.querySelector('.donorName').innerText.toLowerCase();
        const id = row.querySelector('.donorId').innerText.toLowerCase();

        if(name.includes(keyword) || id.includes(keyword)){
            row.style.display = '';
        }else{
            row.style.display = 'none';
        }

    });

});

</script>

</body>
</html>