<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vital Ledger - Donor Directory</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        .sidebar-link{
            @apply flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-red-50 transition;
        }
    </style>
</head>
<body class="bg-gray-100">

<div class="flex min-h-screen">

    

    <!-- Main Content -->
    <main class="flex-1">

        <!-- Top Bar -->
        <header class="bg-white border-b px-8 py-4 flex justify-between items-center">

            <div class="w-96">
                <input
                    id="searchInput"
                    type="text"
                    placeholder="Search donor by ID or name..."
                    class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-red-500 outline-none"
                >
            </div>

            <div class="flex items-center gap-4">
                <button>🔔</button>
                <button>⚙️</button>

                <div class="flex items-center gap-2">
                    <img
                        src="https://i.pravatar.cc/40"
                        class="w-10 h-10 rounded-full"
                    >
                    <span class="font-medium">
                        Admin Sarah
                    </span>
                </div>
            </div>

        </header>

        <div class="p-8">

            <!-- Header -->
            <div class="flex justify-between items-center">

                <div>
                    <h2 class="text-3xl font-bold text-red-800">
                        Donor Directory
                    </h2>

                    <p class="text-gray-500 mt-1">
                        Manage and monitor the hospital's verified blood donor network.
                    </p>
                </div>

                <button class="bg-red-800 hover:bg-red-900 text-white px-5 py-3 rounded-lg font-medium">
                    + Register New Donor
                </button>

            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-8">

                <div class="bg-white rounded-xl border p-5">
                    <p class="text-gray-500 text-sm">TOTAL DONORS</p>
                    <h3 class="text-3xl font-bold mt-2">1,248</h3>
                </div>

                <div class="bg-white rounded-xl border p-5">
                    <p class="text-gray-500 text-sm">ELIGIBLE NOW</p>
                    <h3 class="text-3xl font-bold mt-2">842</h3>
                </div>

                <div class="bg-white rounded-xl border p-5">
                    <p class="text-gray-500 text-sm">RARE TYPES (O-)</p>
                    <h3 class="text-3xl font-bold mt-2">54</h3>
                </div>

                <div class="bg-white rounded-xl border p-5">
                    <p class="text-gray-500 text-sm">DONATIONS (MTD)</p>
                    <h3 class="text-3xl font-bold mt-2">112</h3>
                </div>

            </div>

            <!-- Filters -->
            <div class="bg-white border rounded-xl p-4 mt-8">

                <div class="flex flex-wrap gap-4">

                    <select class="border rounded-lg px-4 py-2">
                        <option>All Blood Types</option>
                        <option>O+</option>
                        <option>O-</option>
                        <option>A+</option>
                        <option>B+</option>
                    </select>

                    <select class="border rounded-lg px-4 py-2">
                        <option>All Eligibility</option>
                        <option>Eligible</option>
                        <option>Ineligible</option>
                    </select>

                    <select class="border rounded-lg px-4 py-2">
                        <option>Last Donation (Any)</option>
                    </select>

                </div>

            </div>

            <?php

            $donors = [
                [
                    "id"=>"#VL-8821",
                    "name"=>"Julianne Hatcher",
                    "blood"=>"O-",
                    "date"=>"Oct 12, 2023",
                    "status"=>"Eligible"
                ],
                [
                    "id"=>"#VL-9045",
                    "name"=>"Marcus Reed",
                    "blood"=>"A+",
                    "date"=>"Jan 04, 2024",
                    "status"=>"Requested"
                ],
                [
                    "id"=>"#VL-7732",
                    "name"=>"Sonia Khan",
                    "blood"=>"B-",
                    "date"=>"Nov 22, 2023",
                    "status"=>"Critical"
                ],
                [
                    "id"=>"#VL-1102",
                    "name"=>"Arthur Lawson",
                    "blood"=>"AB+",
                    "date"=>"Sep 15, 2023",
                    "status"=>"Ineligible"
                ],
                [
                    "id"=>"#VL-3498",
                    "name"=>"Elena Lopez",
                    "blood"=>"O+",
                    "date"=>"Dec 30, 2023",
                    "status"=>"Eligible"
                ]
            ];

            ?>

            <!-- Table -->
            <div class="bg-white rounded-xl border mt-8 overflow-hidden">

                <table class="w-full">

                    <thead class="bg-gray-50">

                    <tr class="text-left text-gray-600">
                        <th class="p-4">Donor ID</th>
                        <th class="p-4">Full Name</th>
                        <th class="p-4">Blood Type</th>
                        <th class="p-4">Last Donation</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Action</th>
                    </tr>

                    </thead>

                    <tbody id="donorTable">

                    <?php foreach($donors as $donor): ?>

                        <tr class="border-t donor-row">

                            <td class="p-4 donor-id">
                                <?= $donor['id']; ?>
                            </td>

                            <td class="p-4 donor-name font-medium">
                                <?= $donor['name']; ?>
                            </td>

                            <td class="p-4">
                                <span class="bg-red-800 text-white px-3 py-1 rounded-full text-sm">
                                    <?= $donor['blood']; ?>
                                </span>
                            </td>

                            <td class="p-4">
                                <?= $donor['date']; ?>
                            </td>

                            <td class="p-4">

                                <?php
                                $colors = [
                                    "Eligible"=>"bg-green-100 text-green-700",
                                    "Requested"=>"bg-blue-100 text-blue-700",
                                    "Critical"=>"bg-red-100 text-red-700",
                                    "Ineligible"=>"bg-gray-100 text-gray-700"
                                ];
                                ?>

                                <span class="px-3 py-1 rounded-full text-sm <?= $colors[$donor['status']] ?>">
                                    <?= $donor['status']; ?>
                                </span>

                            </td>

                            <td class="p-4">
                                <button class="text-red-700 font-medium hover:underline">
                                    View Profile
                                </button>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </main>

</div>

<script>

const searchInput = document.getElementById("searchInput");
const rows = document.querySelectorAll(".donor-row");

searchInput.addEventListener("keyup", function() {

    let value = this.value.toLowerCase();

    rows.forEach(row => {

        let name = row.querySelector(".donor-name")
            .textContent
            .toLowerCase();

        let id = row.querySelector(".donor-id")
            .textContent
            .toLowerCase();

        if(name.includes(value) || id.includes(value)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }

    });

});

</script>

</body>
</html>