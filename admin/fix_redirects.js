const fs = require('fs');
const assignPath = 'c:/wamp64/www/Blood-Donation-and-Request-System/admin/assignments.php';
let assign = fs.readFileSync(assignPath, 'utf8');

assign = assign.replace(/header\('Location: blood_requests_crud\.php'\);/g, "header('Location: assignments.php');");

fs.writeFileSync(assignPath, assign);
console.log('Redirects fixed!');
