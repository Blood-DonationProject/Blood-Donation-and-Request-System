const fs = require('fs');

const crudPath = 'c:/wamp64/www/Blood-Donation-and-Request-System/admin/blood_requests_crud.php';
const assignPath = 'c:/wamp64/www/Blood-Donation-and-Request-System/admin/assignments.php';
const crud = fs.readFileSync(crudPath, 'utf8');
let newCrud = crud;

// 1. PHP Handlers
const assignLogicRegex = /\/\/ Assign donor action[\s\S]*?(?=\/\/ Unassign donor action)/;
const unassignLogicRegex = /\/\/ Unassign donor action[\s\S]*?(?=\nif \(isset\(\$_GET\['delete'\]\)\))/;
const assignLogic = (crud.match(assignLogicRegex) || [''])[0];
const unassignLogic = (crud.match(unassignLogicRegex) || [''])[0];

newCrud = newCrud.replace(assignLogic, '');
newCrud = newCrud.replace(unassignLogic, '');

// 2. Queries
const assignableRegex = /\/\/ Fetch assignable requests \(Pending or Approved without donor\)[\s\S]*?(?=\/\/ Fetch available donors)/;
const availableRegex = /\/\/ Fetch available donors[\s\S]*?(?=\/\/ Pending blood requests for action cards)/;
const assignedRegex = /\/\/ Fetch already assigned requests for display[\s\S]*?(?=\/\/ Latest 5 blood requests for Recent section)/;

const assignableQuery = (crud.match(assignableRegex) || [''])[0];
const availableQuery = (crud.match(availableRegex) || [''])[0];
const assignedQuery = (crud.match(assignedRegex) || [''])[0];

newCrud = newCrud.replace(assignableQuery, '');
newCrud = newCrud.replace(availableQuery, '');
newCrud = newCrud.replace(assignedQuery, '');

// 3. HTML blocks
const donorAssignmentHtmlRegex = /<!-- Donor Assignment Section -->[\s\S]*?(?=<!-- Assigned Donors Summary -->)/;
const activeAssignmentsHtmlRegex = /<!-- Assigned Donors Summary -->[\s\S]*?(?=<!-- Recent Blood Requests -->)/;

const donorAssignmentHtml = (crud.match(donorAssignmentHtmlRegex) || [''])[0];
const activeAssignmentsHtml = (crud.match(activeAssignmentsHtmlRegex) || [''])[0];

newCrud = newCrud.replace(donorAssignmentHtml, '');
newCrud = newCrud.replace(activeAssignmentsHtml, '');

// 4. JS blocks
const donorJsRegex = /\/\/ Donor Assignment Logic with Blood Compatibility Matching[\s\S]*?(?=<\/script>)/;
const donorJs = (crud.match(donorJsRegex) || [''])[0];
newCrud = newCrud.replace(donorJs, '');

// Modals HTML
const assignModalRegex = /<!-- Assign Donor Modal -->[\s\S]*?(?=<!-- Confirmation Modals -->)/;
// Extract just the confirmation modals but leave the complete confirm modal? Wait, blood_requests_crud still needs completeConfirmModal and deleteConfirmModal.
// assignConfirmModal is part of "Confirmation Modals", but completeConfirmModal is also there.
// Let's just match the Assign Donor Modal and Assign Confirm Modal.
const assignConfirmModalRegex = /<div id="assignConfirmModal"[\s\S]*?(?=<div id="completeConfirmModal")/
const assignModal = (crud.match(assignModalRegex) || [''])[0];
const assignConfirmModal = (crud.match(assignConfirmModalRegex) || [''])[0];

newCrud = newCrud.replace(assignModal, '');
newCrud = newCrud.replace(assignConfirmModal, '');

// Modal JS
const modalJsRegex = /var isReassignMode = false;\n\n[\s\S]*?(?=function openCompleteModal)/;
const modalJs = (crud.match(modalJsRegex) || [''])[0];
newCrud = newCrud.replace(modalJs, '');

const executeModalAssignRegex = /function openAssignConfirmModal[\s\S]*?(?=<\/script>)/;
const executeModalAssign = (crud.match(executeModalAssignRegex) || [''])[0];
newCrud = newCrud.replace(executeModalAssign, '');

fs.writeFileSync(crudPath, newCrud);

// Now patch assignments.php
let assign = fs.readFileSync(assignPath, 'utf8');

// Insert PHP Handlers
let phpInsert = '\n' + assignLogic + '\n' + unassignLogic + '\n' + assignableQuery + '\n' + availableQuery + '\n' + assignedQuery + '\n';
assign = assign.replace(/\$error = '';\n\$success = '';\n/, "$error = '';\n$success = '';\n" + phpInsert);

let modifiedDonorAssignmentHtml = donorAssignmentHtml.replace(/blood_requests_crud\.php/g, 'assignments.php');
let modifiedActiveAssignmentsHtml = activeAssignmentsHtml.replace(/blood_requests_crud\.php/g, 'assignments.php');

const mainTableStart = '<!-- Main Table -->';
let htmlInsert = modifiedDonorAssignmentHtml + '\n' + modifiedActiveAssignmentsHtml + '\n';
assign = assign.replace(mainTableStart, htmlInsert + mainTableStart);

const timelineModalStart = '<!-- Timeline Modal -->';
let modalsInsert = assignModal + '\n' + assignConfirmModal + '\n';
assign = assign.replace(timelineModalStart, modalsInsert + timelineModalStart);

const closingBody = '</body>';
let modifiedExecuteModalAssign = executeModalAssign.replace(/form\.action = 'blood_requests_crud\.php';/g, "form.action = 'assignments.php';");
let jsInsert = '<script>\n' + donorJs + '\n' + modalJs + '\n' + modifiedExecuteModalAssign + '\n</script>\n';
assign = assign.replace(closingBody, jsInsert + closingBody);

fs.writeFileSync(assignPath, assign);
console.log('Success!');
